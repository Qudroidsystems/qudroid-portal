<?php

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use App\Services\Finance\OpayGateway;
use App\Services\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();
    config(['services.paystack.active' => false]);
    app(Settings::class)->put([
        'opay.active' => '1', 'opay.mode' => 'sandbox', 'opay.merchant_id' => '256612345678901',
        'opay.test_public_key' => 'OPAYPUB_test', 'opay.test_secret_key' => 'OPAYPRV_test',
    ]);

    $course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $cohort = $course->cohorts()->create(['code' => 'WEB101-26-01', 'name' => 'Cohort 1', 'status' => 'open']);
    $user = User::factory()->create(['user_type' => 'student']);
    $user->assignRole('Student');
    $user->forceFill(['must_change_password' => false])->save();
    $this->me = $user;
    $student = Student::create(['user_id' => $user->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->enrollment = app(EnrollmentService::class)->enroll($student, $cohort, $this->admin);
    $this->invoice = $this->enrollment->invoices()->first();
    $GLOBALS['opayStatus'] = ['status' => 'INITIAL'];
    $GLOBALS['opayRequests'] = [];

    Http::fake([
        'testapi.opaycheckout.com/api/v1/international/cashier/create' => function (HttpRequest $r) {
            $GLOBALS['opayRequests'][] = $r;
            return Http::response(['code' => '00000', 'message' => 'SUCCESSFUL', 'data' => [
                'reference' => $r['reference'], 'orderNo' => '2110001', 'cashierUrl' => 'https://sandboxcashier.opaycheckout.com/x', 'status' => 'INITIAL',
            ]]);
        },
        'testapi.opaycheckout.com/api/v1/international/cashier/status' => function (HttpRequest $r) {
            $GLOBALS['opayRequests'][] = $r;
            return Http::response(['code' => '00000', 'message' => 'SUCCESSFUL', 'data' => $GLOBALS['opayStatus'] + ['reference' => $r['reference']]]);
        },
    ]);
});

test('paying with opay: the cashier is opened with the public key, and only a signed status check confirms it', function () {
    $this->actingAs($this->me)->get(route('billing.invoice', $this->invoice))->assertOk()->assertSee('Pay with OPay');

    $this->actingAs($this->me)->post(route('billing.pay', $this->invoice), ['amount' => '150000', 'gateway' => 'opay'])
        ->assertRedirect('https://sandboxcashier.opaycheckout.com/x');
    $payment = Payment::where('method', 'opay')->firstOrFail();
    $create = $GLOBALS['opayRequests'][0];
    expect($create->header('Authorization')[0])->toBe('Bearer OPAYPUB_test')
        ->and($create->header('MerchantId')[0])->toBe('256612345678901')
        ->and($create['amount'])->toBe(['total' => 15000000, 'currency' => 'NGN'])
        ->and($create['returnUrl'])->toBe(route('billing.return', $payment->reference))
        ->and($create['callbackUrl'])->toBe(route('webhooks.opay'));

    // Back before paying: still pending, nothing confirmed.
    $this->actingAs($this->me)->get(route('billing.return', $payment->reference))->assertRedirect(route('billing.invoice', $this->invoice));
    expect($payment->fresh()->status)->toBe('pending');

    // The status request is signed with the secret key over the exact body.
    $status = collect($GLOBALS['opayRequests'])->last();
    expect($status->header('Authorization')[0])->toBe('Bearer ' . hash_hmac('sha512', $status->body(), 'OPAYPRV_test'));

    $GLOBALS['opayStatus'] = ['status' => 'SUCCESS', 'amount' => ['total' => 15000000, 'currency' => 'NGN']];
    $this->actingAs($this->me)->get(route('billing.return', $payment->reference))->assertRedirect(route('billing.receipt', $payment));
    expect($payment->fresh()->status)->toBe('confirmed')
        ->and($this->invoice->fresh()->status)->toBe('paid')
        ->and($this->enrollment->fresh()->access_granted)->toBeTruthy();
});

test('an opay success for the wrong amount is refused', function () {
    $this->actingAs($this->me)->post(route('billing.pay', $this->invoice), ['amount' => '150000', 'gateway' => 'opay']);
    $payment = Payment::where('method', 'opay')->firstOrFail();
    $GLOBALS['opayStatus'] = ['status' => 'SUCCESS', 'amount' => ['total' => 100, 'currency' => 'NGN']];

    $this->actingAs($this->me)->get(route('billing.return', $payment->reference));
    expect($payment->fresh()->status)->toBe('failed')->and($this->invoice->fresh()->status)->not->toBe('paid');
});

test('the opay callback only prompts a status check; a forged success in the body changes nothing', function () {
    $this->actingAs($this->me)->post(route('billing.pay', $this->invoice), ['amount' => '150000', 'gateway' => 'opay']);
    $payment = Payment::where('method', 'opay')->firstOrFail();

    $this->postJson('/webhook/opay', ['type' => 'transaction-status', 'payload' => ['reference' => $payment->reference, 'status' => 'SUCCESS']])->assertOk();
    expect($payment->fresh()->status)->toBe('pending');

    $GLOBALS['opayStatus'] = ['status' => 'SUCCESS', 'amount' => ['total' => 15000000, 'currency' => 'NGN']];
    $this->postJson('/webhook/opay', ['payload' => ['reference' => $payment->reference]])->assertOk();
    $this->postJson('/webhook/opay', ['payload' => ['reference' => $payment->reference]])->assertOk(); // repeats are harmless
    expect($payment->fresh()->status)->toBe('confirmed')->and(Payment::where('status', 'confirmed')->count())->toBe(1);
});

test('other people cannot use someone else\'s return link', function () {
    $this->actingAs($this->me)->post(route('billing.pay', $this->invoice), ['amount' => '150000', 'gateway' => 'opay']);
    $payment = Payment::where('method', 'opay')->firstOrFail();
    $other = User::factory()->create();
    $other->forceFill(['must_change_password' => false])->save();

    $this->actingAs($other)->get(route('billing.return', $payment->reference))->assertNotFound();
});

test('admins save the opay keys in settings; secrets are encrypted and kept when left blank', function () {
    $this->actingAs($this->admin)->get(route('portal-settings.payments'))->assertOk()->assertSee('OPay')->assertSee(route('webhooks.opay'));

    $this->actingAs($this->admin)->post(route('portal-settings.payments.opay'), [
        'opay_active' => '1', 'opay_mode' => 'live', 'opay_merchant_id' => '999000111',
        'opay_live_public_key' => 'OPAYPUB_live', 'opay_live_secret_key' => 'OPAYPRV_live',
    ])->assertSessionHas('success');
    $raw = \App\Models\Setting::where('key', 'opay.live_secret_key')->value('value');
    expect($raw)->not->toContain('OPAYPRV_live');
    $gateway = app(OpayGateway::class);
    expect($gateway->mode())->toBe('live')->and($gateway->secret())->toBe('OPAYPRV_live')->and($gateway->enabled())->toBeTrue();

    $this->actingAs($this->admin)->post(route('portal-settings.payments.opay'), ['opay_active' => '1', 'opay_mode' => 'live', 'opay_merchant_id' => '999000111']);
    expect(app(OpayGateway::class)->secret())->toBe('OPAYPRV_live');

    // Staff without "Manage settings" can't.
    $staff = User::factory()->create();
    $staff->forceFill(['must_change_password' => false])->save();
    $this->actingAs($staff)->post(route('portal-settings.payments.opay'), ['opay_mode' => 'sandbox'])->assertForbidden();
});

test('the app can pay with opay too', function () {
    \Laravel\Sanctum\Sanctum::actingAs($this->me);
    $this->getJson('/api/v1/billing/invoices')->assertOk()->assertJsonPath('meta.gateways', ['opay']);
    $this->postJson("/api/v1/billing/invoices/{$this->invoice->id}/pay", ['gateway' => 'opay'])->assertCreated()
        ->assertJsonPath('data.gateway', 'opay')->assertJsonPath('data.authorization_url', 'https://sandboxcashier.opaycheckout.com/x');

    $ref = Payment::where('method', 'opay')->value('reference');
    $GLOBALS['opayStatus'] = ['status' => 'SUCCESS', 'amount' => ['total' => 15000000, 'currency' => 'NGN']];
    $this->postJson('/api/v1/billing/payments/verify', ['reference' => $ref])->assertOk()->assertJsonPath('data.status', 'success');
});
