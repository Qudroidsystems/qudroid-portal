<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\BusinessUnit;
use App\Services\Finance\PaystackGateway;
use App\Services\Hr\PayeCalculator;
use App\Services\Settings\Settings;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function __construct(protected Settings $settings) {}

    // ── Payments (Paystack) ─────────────────────────────────────────────

    public function payments(PaystackGateway $paystack)
    {
        $c = $this->settings->paystack();

        return view('settings.payments', [
            'c' => $c,
            'enabled' => $paystack->enabled(),
            'mode' => $paystack->mode(),
            'source' => $this->settings->paystackSource(),
            'masked' => ['test' => $this->mask($c['test_secret_key'] ?? null), 'live' => $this->mask($c['live_secret_key'] ?? null)],
            'saved' => ['test' => $this->settings->has('paystack.test_secret_key'), 'live' => $this->settings->has('paystack.live_secret_key')],
            'last' => $this->settings->lastChange(Settings::PAYSTACK),
        ]);
    }

    public function savePayments(Request $request)
    {
        $d = $request->validate([
            'mode' => ['required', 'in:sandbox,live'],
            'test_public_key' => ['nullable', 'string', 'max:200', 'starts_with:pk_test_'],
            'live_public_key' => ['nullable', 'string', 'max:200', 'starts_with:pk_live_'],
            'test_secret_key' => ['nullable', 'string', 'max:200', 'starts_with:sk_test_'],
            'live_secret_key' => ['nullable', 'string', 'max:200', 'starts_with:sk_live_'],
        ], [
            '*.starts_with' => 'That doesn\'t look like the right key. It should start with :values.',
        ]);

        $values = [
            'paystack.active' => $request->boolean('active') ? '1' : '0',
            'paystack.mode' => $d['mode'],
            'paystack.test_public_key' => trim((string) ($d['test_public_key'] ?? '')),
            'paystack.live_public_key' => trim((string) ($d['live_public_key'] ?? '')),
        ];
        // A blank secret box keeps the saved key; "remove" clears it.
        foreach (['test', 'live'] as $m) {
            if ($request->boolean('clear_' . $m . '_secret')) $values['paystack.' . $m . '_secret_key'] = null;
            elseif (filled($d[$m . '_secret_key'] ?? null)) $values['paystack.' . $m . '_secret_key'] = trim($d[$m . '_secret_key']);
        }
        $this->settings->put($values, $request->user());

        $secret = app(PaystackGateway::class)->secret();
        $note = $d['mode'] === 'live' && $secret === '' ? ' Online payments stay off until a live secret key is saved.' : '';

        return back()->with('success', 'Payment settings saved.' . $note);
    }

    public function testPayments(PaystackGateway $paystack)
    {
        $problem = $paystack->check();

        return back()->with($problem ? 'error' : 'success', $problem
            ? 'Paystack didn\'t accept the ' . ($paystack->mode() === 'live' ? 'live' : 'test') . ' secret key: ' . $problem
            : 'Paystack accepted the ' . ($paystack->mode() === 'live' ? 'live' : 'test') . ' secret key.');
    }

    /** Forget every Paystack value saved here, so the .env values are used again. */
    public function useEnv(Request $request)
    {
        $this->settings->put(array_fill_keys(Settings::PAYSTACK, null), $request->user());

        return back()->with('success', 'Saved Paystack settings removed. The values in .env are used again.');
    }

    protected function mask(?string $key): ?string
    {
        if (!$key) return null;

        return substr($key, 0, 8) . str_repeat('•', 8) . substr($key, -4);
    }

    // ── Business units ──────────────────────────────────────────────────

    // ── Integrations ────────────────────────────────────────────────────

    public function integrations()
    {
        return view('settings.integrations', [
            'giphySaved' => $this->settings->has('giphy.api_key'),
            'giphyEnv'   => (bool) config('services.giphy.key'),
            'giphyHint'  => ($k = $this->settings->giphyKey()) ? '••••' . substr($k, -4) : null,
        ]);
    }

    public function saveIntegrations(Request $request)
    {
        $data = $request->validate([
            'giphy_api_key' => ['nullable', 'string', 'max:100', 'regex:/^[A-Za-z0-9]+$/'],
            'clear_giphy'   => ['nullable', 'boolean'],
        ], ['giphy_api_key.regex' => 'A GIPHY key is letters and numbers only.']);

        if ($request->boolean('clear_giphy')) {
            $this->settings->put(['giphy.api_key' => null], $request->user());

            return back()->with('success', 'GIPHY key removed.');
        }
        if (!empty($data['giphy_api_key'])) {
            $this->settings->put(['giphy.api_key' => $data['giphy_api_key']], $request->user());

            return back()->with('success', 'GIPHY key saved. GIFs are now available in chat.');
        }

        return back()->with('success', 'Nothing changed.');
    }

    public function units()
    {
        $counts = [];
        foreach (['finance_invoices' => 'invoices', 'finance_expenses' => 'expenses', 'crm_deals' => 'deals', 'projects' => 'projects', 'hr_employees' => 'staff', 'academy_courses' => 'courses'] as $table => $label) {
            foreach (\DB::table($table)->whereNotNull('business_unit_id')->selectRaw('business_unit_id, count(*) as n')->groupBy('business_unit_id')->pluck('n', 'business_unit_id') as $id => $n) {
                $counts[$id][$label] = $n;
            }
        }

        return view('settings.units', ['units' => BusinessUnit::ordered()->get(), 'counts' => $counts]);
    }

    public function storeUnit(Request $request)
    {
        $d = $this->unitData($request);
        BusinessUnit::create($d + ['position' => (int) BusinessUnit::max('position') + 1, 'is_active' => true]);

        return back()->with('success', $d['name'] . ' added.');
    }

    public function updateUnit(Request $request, BusinessUnit $unit)
    {
        $d = $this->unitData($request, $unit);
        $active = $request->boolean('is_active');
        if (!$active && $unit->is_active && BusinessUnit::active()->count() === 1) {
            throw ValidationException::withMessages(['is_active' => 'Keep at least one business unit active.']);
        }
        $unit->update($d + ['is_active' => $active, 'position' => max(0, (int) $request->input('position', $unit->position))]);

        return back()->with('success', $unit->name . ' saved.');
    }

    protected function unitData(Request $request, ?BusinessUnit $unit = null): array
    {
        $d = $request->validate([
            'name' => ['required', 'string', 'max:100', \Illuminate\Validation\Rule::unique('business_units', 'name')->ignore($unit)],
            'code' => ['required', 'string', 'max:10', 'alpha_dash', \Illuminate\Validation\Rule::unique('business_units', 'code')->ignore($unit)],
            'color' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'description' => ['nullable', 'string', 'max:255'],
        ]);
        $d['code'] = strtoupper($d['code']);

        return $d;
    }

    // ── Payroll tax ─────────────────────────────────────────────────────

    public function payroll()
    {
        $c = $this->settings->payroll();
        $calc = new PayeCalculator($c);
        $examples = collect([1_000_000, 3_000_000, 5_000_000, 10_000_000, 25_000_000, 50_000_000])->map(function ($naira) use ($calc) {
            $tax = $calc->annualTax($naira * 100)[0] / 100;

            return ['income' => $naira, 'tax' => $tax, 'rate' => $naira ? $tax / $naira * 100 : 0];
        });

        return view('settings.payroll', [
            'c' => $c,
            'custom' => $this->settings->has('payroll.rates'),
            'review' => $this->settings->payrollReview(),
            'examples' => $examples,
            'last' => $this->settings->lastChange(['payroll.rates']),
        ]);
    }

    public function savePayroll(Request $request)
    {
        $d = $request->validate([
            'bands' => ['required', 'array', 'min:1', 'max:12'],
            'bands.*.width' => ['nullable', 'numeric', 'min:1', 'max:100000000000'],
            'bands.*.rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'pension_employee' => ['required', 'numeric', 'min:0', 'max:100'],
            'pension_employer' => ['required', 'numeric', 'min:0', 'max:100'],
            'nhf_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'rent_relief_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'rent_relief_cap' => ['required', 'numeric', 'min:0', 'max:100000000000'],
            'working_days_month' => ['required', 'integer', 'min:1', 'max:31'],
        ]);

        $bands = array_map(fn ($b) => ['width' => $b['width'] ?? null, 'rate' => $b['rate']], array_values($d['bands']));
        foreach ($bands as $i => $b) {
            $last = $i === count($bands) - 1;
            if ($last && filled($b['width'])) throw ValidationException::withMessages(['bands' => 'Leave the last band\'s width empty: it covers everything above.']);
            if (!$last && blank($b['width'])) throw ValidationException::withMessages(['bands' => 'Every band except the last needs a width.']);
        }

        $num = fn ($v) => $v + 0;   // "18.0" → 18, "7.5" → 7.5
        $this->settings->put(['payroll.rates' => [
            'tax_bands' => array_map(fn ($b) => [filled($b['width']) ? (int) round($b['width']) : null, $num($b['rate'])], $bands),
            'pension_employee' => $num($d['pension_employee']),
            'pension_employer' => $num($d['pension_employer']),
            'nhf_rate' => $num($d['nhf_rate']),
            'rent_relief_rate' => $num($d['rent_relief_rate']),
            'rent_relief_cap' => (int) round($d['rent_relief_cap']),
            'working_days_month' => (int) $d['working_days_month'],
        ]], $request->user());

        return back()->with('success', 'Payroll rates saved. They apply the next time a payroll is generated; payslips already worked out keep their figures.');
    }

    /** Record that an accountant checked the current rates. Changing the rates later clears it. */
    public function reviewPayroll(Request $request)
    {
        $d = $request->validate(['reviewer' => ['required', 'string', 'max:120'], 'note' => ['nullable', 'string', 'max:500']]);
        $this->settings->put(['payroll.review' => [
            'name' => $d['reviewer'], 'note' => $d['note'] ?? null, 'at' => now()->toDateTimeString(),
            'by' => $request->user()->name, 'hash' => $this->settings->payrollHash(),
        ]], $request->user());

        return back()->with('success', 'Recorded that ' . $d['reviewer'] . ' checked these rates.');
    }

    public function resetPayroll(Request $request)
    {
        $this->settings->put(['payroll.rates' => null], $request->user());

        return back()->with('success', 'Payroll rates are back to the built-in Nigeria Tax Act 2025 defaults.');
    }
}
