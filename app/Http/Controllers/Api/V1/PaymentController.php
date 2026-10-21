<?php

namespace App\Http\Controllers\Api\V1;

use App\Models\OnlineFeePayment;
use App\Models\Student;
use App\Services\Api\StudentAccess;
use App\Services\Payment\OnlineFeeCheckoutService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

/**
 * Online school-fee payment from the app, on the same checkout service as
 * the web portal (Paystack / OPay):
 *
 *  1. GET  students/{id}/payment-quote  → bills that can be paid now
 *  2. POST payments/checkout            → authorization_url
 *  3. The app opens authorization_url in a WebView. When the gateway sends
 *     the payer to …/api/v1/payments/callback the app closes the WebView.
 *  4. GET  payments/{reference}         → status (re-checks the gateway)
 *
 * The gateway webhook posts the payment either way, so a closed app does
 * not lose a payment.
 */
class PaymentController extends ApiController
{
    public function __construct(protected OnlineFeeCheckoutService $checkout) {}

    public function quote(Request $request, int $student)
    {
        if (!StudentAccess::isOwnOrChild($request->user(), $student)) {
            return $this->fail('You cannot pay for this student.', 403, 'forbidden');
        }

        $model = Student::find($student);
        if (!$model) return $this->fail('Student not found.', 404, 'not_found');

        $row = StudentAccess::row($student);
        $sessionId = (int) ($request->get('session_id') ?: ($row->session_id ?? 0) ?: $this->currentSessionId());
        $termId    = (int) ($request->get('term_id') ?: ($row->term_id ?? 0) ?: $this->currentTermId());

        $q = $this->checkout->quote($model, $termId ?: null, $sessionId ?: null);

        $line = fn ($l) => [
            'key'          => $l['key'],
            'title'        => $l['title'],
            'description'  => $l['description'] ?? null,
            'is_arrear'    => (bool) $l['is_arrear'],
            'term'         => $l['term_name'] ?? null,
            'session'      => $l['session_name'] ?? null,
            'class'        => $l['class_name'] ?? null,
            'original'     => $l['original_kobo'] / 100,
            'savings'      => $l['savings_kobo'] / 100,
            'payable'      => $l['payable_kobo'] / 100,
            'paid'         => $l['paid_kobo'] / 100,
            'balance'      => $l['balance_kobo'] / 100,
        ];

        return $this->ok([
            'student_id'     => $student,
            'session_id'     => $sessionId,
            'term_id'        => $termId,
            'message'        => $q['statementError'] ?? null,
            'current'        => array_map($line, $q['current']),
            'arrears'        => array_map($line, $q['arrears']),
            'totals'         => [
                'current_payable' => $q['totals']['current_payable_kobo'] / 100,
                'current_paid'    => $q['totals']['current_paid_kobo'] / 100,
                'current_balance' => $q['totals']['current_kobo'] / 100,
                'arrears_balance' => $q['totals']['arrears_kobo'] / 100,
            ],
            'gateway_ready'  => $this->checkout->gatewayReady(),
            'gateways'       => array_keys($this->checkout->readyGateways()),
            'min_total'      => OnlineFeeCheckoutService::MIN_TOTAL_KOBO / 100,
            'recent'         => OnlineFeePayment::where('student_id', $student)
                ->whereIn('status', ['success', 'pending', 'amount_mismatch'])
                ->latest()->limit(8)->get()->map(fn ($p) => $this->paymentRow($p))->values(),
        ]);
    }

    /**
     * Body: { student_id, term_id, session_id, gateway?, items: { "<key>": amount_in_naira, ... } }
     * Amounts may be part-payments; the service validates every line.
     */
    public function checkout(Request $request)
    {
        $data = $request->validate([
            'student_id' => 'required|integer',
            'term_id'    => 'required|integer|exists:schoolterm,id',
            'session_id' => 'required|integer|exists:schoolsession,id',
            'items'      => 'required|array|min:1',
            'items.*'    => 'nullable',
            'gateway'    => 'nullable|in:paystack,opay',
        ]);

        $user = $request->user();
        $studentId = (int) $data['student_id'];
        $own = StudentAccess::isOwnOrChild($user, $studentId);
        $staff = false;
        try { $staff = $user->can('Create online-fee-payments'); } catch (\Throwable $e) {}
        if (!$own && !$staff) return $this->fail('You are not allowed to pay for this student.', 403, 'forbidden');

        $student = Student::find($studentId);
        if (!$student) return $this->fail('Student not found.', 404, 'not_found');

        $ready = $this->checkout->readyGateways();
        $gateway = $data['gateway'] ?? (array_key_first($ready) ?: 'paystack');
        if (!array_key_exists($gateway, $ready)) {
            return $this->fail('That payment option is not available right now.', 422, 'gateway_unavailable');
        }

        $items = array_filter(array_map(fn ($v) => is_numeric($v) ? (string) $v : (string) ($v ?? ''), $data['items']), fn ($v) => $v !== '');

        try {
            $payment = $this->checkout->start(
                $student, $user, (int) $data['term_id'], (int) $data['session_id'], $items,
                route('api.v1.payments.callback'), $gateway
            );
        } catch (ValidationException $e) {
            $first = collect($e->errors())->flatten()->first();
            return $this->fail($first ?: 'Please check the amounts.', 422, 'invalid_selection', $e->errors());
        } catch (\Throwable $e) {
            Log::error('API online fee checkout failed', ['student' => $studentId, 'error' => $e->getMessage()]);
            return $this->fail('Could not start the payment. Please try again.', 500, 'checkout_failed');
        }

        return $this->ok([
            'reference'         => $payment->reference,
            'authorization_url' => $payment->authorization_url,
            'callback_url'      => route('api.v1.payments.callback'),
            'amount'            => $payment->amount_kobo / 100,
            'gateway'           => $gateway,
        ], 'Payment started.', 201);
    }

    public function show(Request $request, string $reference)
    {
        $payment = OnlineFeePayment::with('items')->where('reference', $reference)->first();
        if (!$payment) return $this->fail('Payment not found.', 404, 'not_found');
        if (!$this->mayView($request, $payment)) return $this->fail('Not allowed.', 403, 'forbidden');

        if ($payment->status === 'pending' && !$payment->posted_at
            && (!$payment->last_verified_at || $payment->last_verified_at->lt(now()->subSeconds(10)))) {
            $payment = ($this->checkout->finalize($reference, null, 'status_check') ?? $payment)->load('items');
        }

        $out = $this->paymentRow($payment);
        $out['items'] = $payment->items->map(fn ($i) => [
            'title'     => $i->title,
            'is_arrear' => (bool) $i->is_arrear,
            'amount'    => $i->amount_kobo / 100,
            'applied'   => $i->applied_kobo / 100,
        ])->values();
        $out['student'] = optional(StudentAccess::row((int) $payment->student_id), fn ($s) => [
            'id' => (int) $s->id, 'name' => trim($s->firstname . ' ' . $s->lastname), 'admission_no' => $s->admissionNo,
        ]);

        return $this->ok($out);
    }

    /** Payment history for one student (all terms). */
    public function history(Request $request, int $student)
    {
        if (!StudentAccess::canView($request->user(), $student)) {
            return $this->fail('This student is not linked to your account.', 403, 'forbidden');
        }
        $page = OnlineFeePayment::where('student_id', $student)->latest()->paginate(20);
        return $this->paged($page, fn ($p) => $this->paymentRow($p));
    }

    /**
     * Where the gateway sends the payer back. Not signed in (it runs inside
     * the WebView); it only asks the gateway to confirm, then shows a short
     * page. The app watches for this URL and closes the WebView.
     */
    public function callback(Request $request)
    {
        $reference = (string) ($request->query('reference') ?: $request->query('trxref') ?: $request->query('orderNo'));
        $status = 'unknown';
        if ($reference !== '') {
            try {
                $p = $this->checkout->finalize($reference, null, 'callback');
                $status = $p->status ?? 'unknown';
            } catch (\Throwable $e) {
                Log::warning('API payment callback finalize failed', ['ref' => $reference, 'error' => $e->getMessage()]);
            }
        }

        $label = OnlineFeePayment::STATUS_LABELS[$status] ?? 'Processing';
        $ref = e($reference);
        $html = <<<HTML
<!doctype html><html><head><meta name="viewport" content="width=device-width,initial-scale=1"><title>Payment</title>
<style>body{font-family:system-ui,sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;background:#f5f7fb;color:#1e3a5f}
.c{text-align:center;padding:24px}h1{font-size:20px}p{color:#555}</style></head>
<body><div class="c"><h1>{$label}</h1><p>Reference: {$ref}</p><p>You can return to the app.</p></div></body></html>
HTML;

        return response($html, 200)->header('Content-Type', 'text/html');
    }

    // ── helpers ─────────────────────────────────────────────────────────

    protected function mayView(Request $request, OnlineFeePayment $p): bool
    {
        $u = $request->user();
        if ((int) $p->payer_user_id === (int) $u->id) return true;
        if (StudentAccess::isOwnOrChild($u, (int) $p->student_id)) return true;
        try { return $u->can('View online-fee-payments'); } catch (\Throwable $e) { return false; }
    }

    protected function paymentRow(OnlineFeePayment $p): array
    {
        return [
            'reference'    => $p->reference,
            'student_id'   => (int) $p->student_id,
            'status'       => $p->status,
            'status_label' => OnlineFeePayment::STATUS_LABELS[$p->status] ?? ucfirst((string) $p->status),
            'posted'       => (bool) $p->posted_at,
            'gateway'      => $p->gateway,
            'channel'      => $p->channel,
            'amount'       => $p->amount_kobo / 100,
            'paid'         => $p->paid_kobo !== null ? $p->paid_kobo / 100 : null,
            'term'         => $p->term_id ? DB::table('schoolterm')->where('id', $p->term_id)->value('term') : null,
            'session'      => $p->session_id ? DB::table('schoolsession')->where('id', $p->session_id)->value('session') : null,
            'paid_at'      => optional($p->paid_at)->toIso8601String(),
            'created_at'   => optional($p->created_at)->toIso8601String(),
            'failure_reason' => $p->failure_reason,
        ];
    }
}
