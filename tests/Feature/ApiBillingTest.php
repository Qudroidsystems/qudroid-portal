<?php

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Finance\Payment;
use App\Models\User;
use App\Services\Academy\EnrollmentService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::where('email', config('qudroid.super_admin.email'))->first();
    config(['services.paystack.active' => true, 'services.paystack.mode' => 'sandbox', 'services.paystack.test_secret_key' => 'sk_test_abc']);
    $course = Course::create(['code' => 'WEB101', 'title' => 'Web Foundations', 'fee' => 150000, 'currency' => 'NGN', 'status' => 'published']);
    $cohort = $course->cohorts()->create(['code' => 'WEB101-26-01', 'name' => 'Cohort 1', 'status' => 'open']);
    $this->me = User::factory()->create(['user_type' => 'student']);
    $this->me->assignRole('Student');
    $student = Student::create(['user_id' => $this->me->id, 'student_no' => Student::nextNumber(), 'status' => 'admitted']);
    $this->invoice = app(EnrollmentService::class)->enroll($student, $cohort, $admin, ['discount_amount' => 50000])->invoices()->first();
    $GLOBALS['psVerify'] = [];
    Http::fake([
        'api.paystack.co/transaction/initialize' => Http::response(['status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/abc']]),
        'api.paystack.co/transaction/verify/*' => fn () => Http::response(['status' => true, 'data' => $GLOBALS['psVerify']]),
    ]);
});

test('the app lists bills in kobo with labels, and only your own', function () {
    Sanctum::actingAs($this->me);
    $this->getJson(route('api.v1.billing.invoices', ['status' => 'open']))->assertOk()
        ->assertJsonPath('data.0.number', $this->invoice->number)
        ->assertJsonPath('data.0.balance', 10000000)->assertJsonPath('data.0.balance_label', '₦100,000.00')
        ->assertJsonPath('data.0.can_pay', true)->assertJsonPath('data.0.min_payment', 10000000)
        ->assertJsonPath('meta.balance_total', 10000000);
    $this->getJson(route('api.v1.billing.invoices.show', $this->invoice))->assertOk()->assertJsonPath('data.items.0.unit_price_label', '₦150,000.00')->assertJsonPath('data.items.0.amount_label', '₦100,000.00');

    Sanctum::actingAs(User::factory()->create(['user_type' => 'student']));
    $this->getJson(route('api.v1.billing.invoices.show', $this->invoice))->assertNotFound();
    $this->getJson(route('api.v1.billing.invoices'))->assertOk()->assertJsonCount(0, 'data');
});

test('paying in the app is confirmed only after Paystack says so', function () {
    Sanctum::actingAs($this->me);
    $this->postJson(route('api.v1.billing.pay', $this->invoice), ['amount' => 50000])->assertStatus(422)->assertJsonPath('code', 'invalid_amount');
    $start = $this->postJson(route('api.v1.billing.pay', $this->invoice))->assertCreated()
        ->assertJsonPath('data.authorization_url', 'https://checkout.paystack.com/abc')
        ->assertJsonPath('data.callback_url', route('billing.paystack.app-return'))->json('data');
    $payment = Payment::where('reference', $start['reference'])->firstOrFail();

    // abandoned: still pending, nothing confirmed
    $GLOBALS['psVerify'] = ['status' => 'abandoned', 'reference' => $payment->reference, 'amount' => 10000000, 'currency' => 'NGN'];
    $this->postJson(route('api.v1.billing.verify'), ['reference' => $payment->reference])->assertOk()->assertJsonPath('data.status', 'pending');

    // success for the right amount: confirmed, invoice paid; verifying again changes nothing
    $GLOBALS['psVerify'] = ['status' => 'success', 'reference' => $payment->reference, 'amount' => 10000000, 'currency' => 'NGN', 'channel' => 'card'];
    $this->postJson(route('api.v1.billing.verify'), ['reference' => $payment->reference])->assertOk()
        ->assertJsonPath('data.status', 'success')->assertJsonPath('data.invoice.status', 'paid')->assertJsonPath('data.invoice.balance', 0);
    $this->postJson(route('api.v1.billing.verify'), ['reference' => $payment->reference])->assertJsonPath('data.status', 'success');
    expect(Payment::where('status', 'confirmed')->count())->toBe(1);

    $this->getJson(route('api.v1.billing.payments'))->assertOk()->assertJsonPath('data.0.amount', 10000000)->assertJsonPath('data.0.method', 'paystack');
    $this->get(route('api.v1.billing.payments.receipt', $payment))->assertOk();

    // someone else's reference
    Sanctum::actingAs(User::factory()->create());
    $this->postJson(route('api.v1.billing.verify'), ['reference' => $payment->reference])->assertNotFound();
});

test('the page Paystack returns app payers to opens without signing in', function () {
    $this->get(route('billing.paystack.app-return', ['reference' => 'x']))->assertOk()->assertSee('go back to the app');
});
