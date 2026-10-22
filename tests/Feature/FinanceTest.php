<?php

use App\Models\Academy\Course;
use App\Models\Academy\Enrollment;
use App\Models\Academy\Student;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();
    config(['services.paystack.active' => true, 'services.paystack.mode' => 'sandbox', 'services.paystack.test_secret_key' => 'sk_test_abc']);

    $this->course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $this->cohort = $this->course->cohorts()->create(['code' => 'WEB101-26-01', 'name' => 'Cohort 1', 'status' => 'open']);

    $user = User::factory()->create(['user_type' => 'student']);
    $user->assignRole('Student');
    $user->forceFill(['must_change_password' => false])->save();
    $this->student = Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->enrollment = app(EnrollmentService::class)->enroll($this->student, $this->cohort, $this->admin, ['discount_amount' => 50000]);
    $this->invoice = $this->enrollment->invoices()->first();
});

/** Fake Paystack. Calling it again changes what the verify endpoint answers. */
function fakePaystack(array $verify): void
{
    $first = !array_key_exists('paystackVerify', $GLOBALS);
    $GLOBALS['paystackVerify'] = $verify;
    if ($first || !app()->bound('paystack.faked')) {
        app()->instance('paystack.faked', true);
        Http::fake([
            'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']]),
            'api.paystack.co/transaction/verify/*'   => fn () => Http::response(['status' => true, 'data' => $GLOBALS['paystackVerify']]),
        ]);
    }
}

test('enrolling issues a numbered course-fee invoice for the net fee', function () {
    expect($this->invoice)->not->toBeNull()
        ->and($this->invoice->number)->toBe('QUD-INV-' . now()->year . '-0001')
        ->and($this->invoice->total)->toBe('100000.00')
        ->and($this->invoice->discount_total)->toBe('50000.00')
        ->and($this->invoice->status)->toBe('issued')
        ->and($this->invoice->user_id)->toBe($this->student->user_id)
        ->and($this->invoice->items)->toHaveCount(1);
});

test('finance pages open for the super administrator', function (string $route, ?string $param = null) {
    $p = Payment::create(['user_id' => $this->student->user_id, 'amount' => 10, 'method' => 'cash', 'status' => 'pending']);
    $params = match ($param) { 'invoice' => [$this->invoice], 'payment' => [$p], default => [] };
    $this->actingAs($this->admin)->get(route($route, $params))->assertOk();
})->with([
    ['finance.index'], ['finance.invoices.index'], ['finance.invoices.create'], ['finance.invoices.show', 'invoice'],
    ['finance.payments.index'], ['finance.payments.show', 'payment'], ['billing.index'],
]);

test('a recorded bank transfer issues a receipt, pays the invoice and opens course access', function () {
    $this->actingAs($this->admin)->post(route('finance.payments.store', $this->invoice), [
        'amount' => '100000', 'method' => 'bank_transfer', 'reference' => 'GTB-778', 'paid_at' => today()->toDateString(),
    ])->assertSessionHasNoErrors();

    $payment = Payment::where('reference', 'GTB-778')->first();
    expect($payment->status)->toBe('confirmed')
        ->and($payment->number)->toBe('QUD-RCT-' . now()->year . '-0001')
        ->and($payment->confirmed_by)->toBe($this->admin->id)
        ->and($this->invoice->fresh()->status)->toBe('paid')
        ->and($this->invoice->fresh()->balance())->toBe('0.00')
        ->and($this->enrollment->fresh()->access_granted)->toBeTrue()
        ->and($this->enrollment->fresh()->access_reason)->toBe('Payment confirmed ' . $payment->number);
});

test('part payment leaves access locked unless instalments are allowed', function () {
    $this->actingAs($this->admin)->post(route('finance.payments.store', $this->invoice), ['amount' => '40000', 'method' => 'cash', 'paid_at' => today()->toDateString()])->assertSessionHasNoErrors();
    expect($this->invoice->fresh()->status)->toBe('partially_paid')
        ->and($this->invoice->fresh()->balance())->toBe('60000.00')
        ->and($this->enrollment->fresh()->access_granted)->toBeFalse();

    // Course now allows instalments with a ₦30,000 first payment: the next payment opens access.
    $this->course->update(['installments_allowed' => true, 'min_deposit' => 30000]);
    $this->actingAs($this->admin)->post(route('finance.payments.store', $this->invoice), ['amount' => '1000', 'method' => 'cash', 'paid_at' => today()->toDateString()])->assertSessionHasNoErrors();
    expect($this->enrollment->fresh()->access_granted)->toBeTrue();
});

test('manual payments are checked', function () {
    $post = fn (array $d) => $this->actingAs($this->admin)->post(route('finance.payments.store', $this->invoice), $d + ['method' => 'cash', 'paid_at' => today()->toDateString()]);

    $post(['amount' => '100000.01'])->assertSessionHasErrors('amount');            // more than the balance
    $post(['amount' => '10', 'method' => 'paystack'])->assertSessionHasErrors('method'); // online is confirmed by Paystack only
    $post(['amount' => '10', 'paid_at' => today()->addDay()->toDateString()])->assertSessionHasErrors('paid_at');
    $post(['amount' => '10', 'reference' => 'R1'])->assertSessionHasNoErrors();
    $post(['amount' => '10', 'reference' => 'R1'])->assertSessionHasErrors('reference'); // duplicate reference

    $staff = User::factory()->create();
    $staff->assignRole('Staff');
    $this->actingAs($staff)->post(route('finance.payments.store', $this->invoice), ['amount' => '10', 'method' => 'cash', 'paid_at' => today()->toDateString()])->assertForbidden();
    $this->actingAs($staff)->get(route('finance.invoices.index'))->assertForbidden();
});

test('reversing a payment restores the balance and locks access it opened', function () {
    $this->actingAs($this->admin)->post(route('finance.payments.store', $this->invoice), ['amount' => '100000', 'method' => 'bank_transfer', 'paid_at' => today()->toDateString()]);
    $payment = Payment::first();

    $officer = User::factory()->create();
    $officer->assignRole('Finance Officer');
    $this->actingAs($officer)->post(route('finance.payments.reverse', $payment), ['reason' => 'x'])->assertForbidden();

    $this->actingAs($this->admin)->post(route('finance.payments.reverse', $payment), ['reason' => 'Transfer bounced'])->assertSessionHasNoErrors();

    expect($payment->fresh()->status)->toBe('reversed')
        ->and($payment->fresh()->number)->not->toBeNull()
        ->and($this->invoice->fresh()->status)->toBe('issued')
        ->and($this->invoice->fresh()->balance())->toBe('100000.00')
        ->and($this->enrollment->fresh()->access_granted)->toBeFalse()
        ->and($this->enrollment->fresh()->status)->toBe('pending');
});

test('an invoice with payments cannot be voided; an unpaid one can', function () {
    $this->actingAs($this->admin)->post(route('finance.payments.store', $this->invoice), ['amount' => '10', 'method' => 'cash', 'paid_at' => today()->toDateString()]);
    $this->actingAs($this->admin)->post(route('finance.invoices.void', $this->invoice), ['reason' => 'Wrong'])->assertSessionHasErrors('reason');

    $this->actingAs($this->admin)->post(route('finance.payments.reverse', Payment::first()), ['reason' => 'Mistake']);
    $this->actingAs($this->admin)->post(route('finance.invoices.void', $this->invoice), ['reason' => 'Wrong'])->assertSessionHasNoErrors();
    expect($this->invoice->fresh()->status)->toBe('void')->and($this->invoice->fresh()->balance())->toBe('0.00');
});

test('withdrawing an enrollment voids its unpaid invoice, and re-enrolling issues a new one', function () {
    app(EnrollmentService::class)->end($this->enrollment, $this->admin, 'withdrawn');
    expect($this->invoice->fresh()->status)->toBe('void');

    app(EnrollmentService::class)->enroll($this->student, $this->cohort, $this->admin);
    expect(Invoice::where('enrollment_id', $this->enrollment->id)->where('status', 'issued')->count())->toBe(1);
});

test('staff can issue a manual invoice to a client', function () {
    $client = User::factory()->create(['user_type' => 'client']);
    $this->actingAs($this->admin)->post(route('finance.invoices.store'), [
        'user_id' => $client->id, 'title' => 'Website', 'currency' => 'NGN', 'due_date' => today()->addWeek()->toDateString(),
        'lines' => [
            ['description' => 'Design', 'quantity' => '2', 'unit_price' => '250000.50', 'discount' => '0.50'],
            ['description' => 'Hosting (12 months)', 'quantity' => '1', 'unit_price' => '60000', 'discount' => '10000'],
            ['description' => '', 'quantity' => '1', 'unit_price' => ''],
        ],
    ])->assertRedirect();

    $inv = Invoice::where('user_id', $client->id)->first();
    expect($inv->items)->toHaveCount(2)
        ->and($inv->subtotal)->toBe('560001.00')       // 2 × 250,000.50 + 60,000
        ->and($inv->discount_total)->toBe('10000.50')
        ->and($inv->total)->toBe('550000.50');
});

test('paying online: paystack is verified on the server before anything is confirmed', function () {
    fakePaystack([]);
    $me = $this->student->user;

    // Must pay at least the full balance when instalments are not allowed.
    $this->actingAs($me)->post(route('billing.pay', $this->invoice), ['amount' => '500'])->assertSessionHasErrors('amount');
    $this->actingAs($me)->post(route('billing.pay', $this->invoice), ['amount' => '100000'])->assertRedirect('https://checkout.paystack.com/abc');
    $payment = Payment::where('method', 'paystack')->first();
    expect($payment->status)->toBe('pending')->and($payment->number)->toBeNull();

    // Paystack says it was abandoned: nothing is confirmed even though the user came back.
    fakePaystack(['status' => 'abandoned', 'reference' => $payment->reference, 'amount' => 10000000, 'currency' => 'NGN']);
    $this->actingAs($me)->get(route('billing.paystack.callback', ['reference' => $payment->reference]))->assertRedirect(route('billing.invoice', $this->invoice));
    expect($payment->fresh()->status)->toBe('pending')->and($this->enrollment->fresh()->access_granted)->toBeFalse();

    // Paystack says success for the right amount: confirmed, receipt, access open.
    fakePaystack(['status' => 'success', 'reference' => $payment->reference, 'amount' => 10000000, 'currency' => 'NGN', 'channel' => 'card']);
    $this->actingAs($me)->get(route('billing.paystack.callback', ['reference' => $payment->reference]))->assertRedirect(route('billing.receipt', $payment));
    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($this->invoice->fresh()->status)->toBe('paid')
        ->and($this->enrollment->fresh()->access_granted)->toBeTrue();

    // A second callback (or webhook) doesn't pay twice.
    $this->actingAs($me)->get(route('billing.paystack.callback', ['reference' => $payment->reference]));
    expect(\App\Models\Finance\PaymentAllocation::count())->toBe(1);

    $this->actingAs($me)->get(route('billing.receipt', $payment))->assertOk()->assertSee($payment->number);
});

test('a paystack success for the wrong amount is not accepted', function () {
    fakePaystack([]);
    $me = $this->student->user;
    $this->actingAs($me)->post(route('billing.pay', $this->invoice), ['amount' => '100000']);
    $payment = Payment::where('method', 'paystack')->first();

    fakePaystack(['status' => 'success', 'reference' => $payment->reference, 'amount' => 100, 'currency' => 'NGN']);
    $this->actingAs($me)->get(route('billing.paystack.callback', ['reference' => $payment->reference]));

    expect($payment->fresh()->status)->toBe('failed')->and($this->invoice->fresh()->status)->toBe('issued');
});

test('the paystack webhook needs a valid signature and then confirms the payment', function () {
    fakePaystack([]);
    $this->actingAs($this->student->user)->post(route('billing.pay', $this->invoice), ['amount' => '100000']);
    $payment = Payment::where('method', 'paystack')->first();
    auth()->logout();

    $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $payment->reference]]);
    $this->call('POST', '/webhook/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => 'forged'], $body)->assertStatus(401);
    expect($payment->fresh()->status)->toBe('pending');

    fakePaystack(['status' => 'success', 'reference' => $payment->reference, 'amount' => 10000000, 'currency' => 'NGN']);
    $sig = hash_hmac('sha512', $body, 'sk_test_abc');
    $this->call('POST', '/webhook/paystack', [], [], [], ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $sig], $body)->assertOk();
    expect($payment->fresh()->status)->toBe('confirmed')->and($this->enrollment->fresh()->access_granted)->toBeTrue();
});

test('people only see their own bills', function () {
    $other = User::factory()->create(['user_type' => 'student']);
    $other->forceFill(['must_change_password' => false])->save();

    $this->actingAs($this->student->user)->get(route('billing.index'))->assertOk()->assertSee($this->invoice->number);
    $this->actingAs($this->student->user)->get(route('billing.invoice', $this->invoice))->assertOk()->assertSee('Pay');
    $this->actingAs($other)->get(route('billing.invoice', $this->invoice))->assertNotFound();
    $this->actingAs($other)->post(route('billing.pay', $this->invoice), ['amount' => '100000'])->assertNotFound();
    $this->actingAs($this->student->user)->get(route('academy.my'))->assertOk()->assertSee('Pay ₦100,000.00', false);
});

test('without paystack keys, students are told to pay by transfer', function () {
    config(['services.paystack.test_secret_key' => null, 'services.paystack.secret_key' => null]);
    $this->actingAs($this->student->user)->get(route('billing.invoice', $this->invoice))->assertOk()->assertSee('Online payment is not available yet');
});
