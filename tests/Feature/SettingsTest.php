<?php

use App\Models\Hr\Employee;
use App\Models\Setting;
use App\Models\User;
use App\Services\Finance\PaystackGateway;
use App\Services\Hr\PayeCalculator;
use App\Services\Settings\Settings;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

function settingsUser(array $perms = []): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->assignRole('Staff');
    if ($perms) $u->givePermissionTo($perms);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    config(['services.paystack.active' => false, 'services.paystack.mode' => 'sandbox', 'services.paystack.secret_key' => null,
        'services.paystack.test_secret_key' => null, 'services.paystack.live_secret_key' => null]);
    $this->admin = settingsUser(['Manage settings']);
});

test('Paystack keys saved in the portal are encrypted, used instead of .env, and never shown back', function () {
    expect(app(PaystackGateway::class)->enabled())->toBeFalse();
    $this->actingAs(settingsUser())->get(route('portal-settings.payments'))->assertForbidden();

    $this->actingAs($this->admin)->post(route('portal-settings.payments.save'), [
        'active' => '1', 'mode' => 'sandbox', 'test_public_key' => 'pk_test_123', 'test_secret_key' => 'sk_test_supersecret9876',
    ])->assertSessionHasNoErrors();

    $raw = Setting::where('key', 'paystack.test_secret_key')->value('value');
    expect($raw)->not->toContain('supersecret')
        ->and(app(PaystackGateway::class)->enabled())->toBeTrue()
        ->and(app(PaystackGateway::class)->secret())->toBe('sk_test_supersecret9876');

    $this->actingAs($this->admin)->get(route('portal-settings.payments'))->assertOk()
        ->assertDontSee('sk_test_supersecret9876')->assertSee('9876')->assertSee(route('webhooks.paystack'));

    // the activity log never records the secret
    expect(\DB::table('activity_logs')->where('properties', 'like', '%supersecret%')->exists())->toBeFalse();

    // a blank secret keeps the saved one; a wrong kind of key is refused
    $this->actingAs($this->admin)->post(route('portal-settings.payments.save'), ['active' => '1', 'mode' => 'sandbox', 'test_public_key' => 'pk_test_123']);
    expect(app(PaystackGateway::class)->secret())->toBe('sk_test_supersecret9876');
    $this->actingAs($this->admin)->post(route('portal-settings.payments.save'), ['mode' => 'live', 'live_secret_key' => 'sk_test_oops'])
        ->assertSessionHasErrors('live_secret_key');

    // switching to live with no live key turns online payments off
    $this->actingAs($this->admin)->post(route('portal-settings.payments.save'), ['active' => '1', 'mode' => 'live']);
    expect(app(PaystackGateway::class)->enabled())->toBeFalse();

    // webhooks are checked against the saved key
    $this->actingAs($this->admin)->post(route('portal-settings.payments.save'), ['active' => '1', 'mode' => 'live', 'live_secret_key' => 'sk_live_abc']);
    $body = '{"event":"charge.success","data":{"reference":"nope"}}';
    expect(app(PaystackGateway::class)->validSignature($body, hash_hmac('sha512', $body, 'sk_live_abc')))->toBeTrue();

    // back to .env
    $this->actingAs($this->admin)->post(route('portal-settings.payments.env'));
    expect(Setting::where('key', 'like', 'paystack.%')->count())->toBe(0)->and(app(PaystackGateway::class)->enabled())->toBeFalse();
});

test('testing the key asks Paystack', function () {
    app(Settings::class)->put(['paystack.active' => '1', 'paystack.test_secret_key' => 'sk_test_good']);
    Http::fake(['api.paystack.co/balance' => Http::sequence()
        ->push(['status' => true, 'data' => []])
        ->push(['status' => false, 'message' => 'Invalid key'], 401)]);

    $this->actingAs($this->admin)->post(route('portal-settings.payments.test'))->assertSessionHas('success');
    $this->actingAs($this->admin)->post(route('portal-settings.payments.test'))->assertSessionHas('error', fn ($m) => str_contains($m, 'Invalid key'));
    Http::assertSent(fn ($r) => $r->hasHeader('Authorization', 'Bearer sk_test_good'));
});

test('payroll rates can be changed, checked by an accountant, and reset', function () {
    $hr = settingsUser(['Manage payroll']);
    $this->actingAs(settingsUser())->get(route('portal-settings.payroll'))->assertForbidden();
    $this->actingAs($hr)->get(route('portal-settings.payroll'))->assertOk()->assertSee('Nobody has confirmed these rates yet');
    $this->actingAs($hr)->get(route('portal-settings.payments'))->assertForbidden();

    $form = ['bands' => [['width' => 1000000, 'rate' => 0], ['width' => '', 'rate' => 10]],
        'pension_employee' => 8, 'pension_employer' => 10, 'nhf_rate' => 2.5, 'rent_relief_rate' => 20, 'rent_relief_cap' => 500000, 'working_days_month' => 22];

    // the last band has no width; others must
    $this->actingAs($hr)->post(route('portal-settings.payroll.save'), array_merge($form, ['bands' => [['width' => '', 'rate' => 0], ['width' => '', 'rate' => 10]]]))
        ->assertSessionHasErrors('bands');

    $this->actingAs($hr)->post(route('portal-settings.payroll.save'), $form)->assertSessionHasNoErrors();
    $calc = new PayeCalculator();
    expect($calc->annualTax(3_000_000 * 100)[0])->toBe(200_000 * 100);   // 10% of the ₦2m above ₦1m

    $this->actingAs($hr)->post(route('portal-settings.payroll.review'), ['reviewer' => 'Ngozi Okafor, ACA'])->assertSessionHasNoErrors();
    $this->actingAs($hr)->get(route('portal-settings.payroll'))->assertSee('Checked by')->assertSee('Ngozi Okafor');
    $this->actingAs($hr)->get(route('hr.payroll.index'))->assertOk()->assertDontSee('confirmed by an accountant');

    // changing a rate clears the check
    $this->actingAs($hr)->post(route('portal-settings.payroll.save'), array_merge($form, ['nhf_rate' => 3]));
    expect(app(Settings::class)->payrollReview())->toBeNull();
    $this->actingAs($hr)->get(route('hr.payroll.index'))->assertSee('confirmed by an accountant');

    $this->actingAs($hr)->post(route('portal-settings.payroll.reset'));
    expect(app(Settings::class)->payroll()['tax_bands'])->toBe(config('qudroid.payroll.tax_bands'));
});
