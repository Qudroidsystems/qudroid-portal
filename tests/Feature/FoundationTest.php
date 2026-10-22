<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->admin = User::where('email', config('qudroid.super_admin.email'))->first();
    $this->admin->forceFill(['must_change_password' => false])->save();
});

test('seeding creates the roles, permissions and a super administrator', function () {
    expect(\Spatie\Permission\Models\Role::count())->toBe(11)
        ->and($this->admin)->not->toBeNull()
        ->and($this->admin->isSuperAdmin())->toBeTrue()
        ->and($this->admin->can('Manage payroll'))->toBeTrue();
});

test('the super administrator can open every foundation page', function (string $route, array $params = []) {
    $this->actingAs($this->admin)->get(route($route, $params))->assertOk();
})->with([
    ['dashboard'],
    ['users.index'],
    ['roles.index'],
    ['permissions.index'],
    ['notifications.index'],
    ['activity.index'],
    ['online-staff.index'],
]);

test('profile pages open for the owner', function () {
    $this->actingAs($this->admin)->get(route('users.overview', $this->admin->id))->assertOk();
    $this->actingAs($this->admin)->get(route('profile.settings', $this->admin->id))->assertOk();
    $this->actingAs($this->admin)->get(route('users.show', $this->admin->id))->assertOk();
});

test('role pages open', function () {
    $role = \Spatie\Permission\Models\Role::where('name', 'Staff')->first();
    $this->actingAs($this->admin)->get(route('roles.show', $role->id))->assertOk();
    $this->actingAs($this->admin)->get(route('roles.edit', $role->id))->assertRedirect(route('roles.show', $role->id));
    $this->actingAs($this->admin)->get(route('roles.adduser', $role->id))->assertOk();
});

test('a staff member without admin permissions is kept out of user management', function () {
    $staff = User::factory()->create();
    $staff->assignRole('Staff');

    $this->actingAs($staff)->get(route('dashboard'))->assertOk();
    $this->actingAs($staff)->get(route('users.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('roles.index'))->assertForbidden();
    $this->actingAs($staff)->get(route('profile.settings', $this->admin->id))->assertForbidden();
    $this->actingAs($staff)->get(route('profile.settings', $staff->id))->assertOk();
});

test('an administrator can create, update, disable and delete a user', function () {
    $res = $this->actingAs($this->admin)->postJson(route('users.store'), [
        'name' => 'Ada Obi', 'email' => 'ada@example.com', 'user_type' => 'client',
        'roles' => ['Client'], 'password' => 'Secret123', 'password_confirmation' => 'Secret123',
    ])->assertCreated();

    $user = User::where('email', 'ada@example.com')->first();
    expect($user->user_type)->toBe('client')
        ->and($user->hasRole('Client'))->toBeTrue()
        ->and($user->must_change_password)->toBeTrue();

    $this->actingAs($this->admin)->putJson(route('users.update', $user->id), [
        'name' => 'Ada N. Obi', 'email' => 'ada@example.com', 'user_type' => 'client', 'roles' => ['Client', 'Staff'],
    ])->assertOk();
    expect($user->fresh()->name)->toBe('Ada N. Obi')->and($user->fresh()->hasRole('Staff'))->toBeTrue();

    $this->actingAs($this->admin)->postJson(route('users.toggle-status', $user->id))->assertOk();
    expect($user->fresh()->is_disabled)->toBeTrue();

    $this->actingAs($this->admin)->deleteJson(route('users.destroy', $user->id))->assertOk();
    expect(User::find($user->id))->toBeNull();
});

test('the last super administrator cannot be removed', function () {
    $this->actingAs($this->admin)->deleteJson(route('users.destroy', $this->admin->id))->assertStatus(422);

    $role = \Spatie\Permission\Models\Role::where('name', 'Super Administrator')->first();
    $this->actingAs($this->admin)->delete(route('roles.destroy', $role->id));
    expect(\Spatie\Permission\Models\Role::find($role->id))->not->toBeNull();
});

test('a temporary password must be changed before using the portal', function () {
    $user = User::factory()->create(['password' => bcrypt('Temp1234')]);
    $user->forceFill(['must_change_password' => true])->save();

    $this->actingAs($user)->get(route('dashboard'))->assertRedirect(route('password.change'));
    $this->actingAs($user)->get(route('password.change'))->assertOk();
    $this->actingAs($user)->post(route('password.change.update'), [
        'password' => 'MyOwnPass9', 'password_confirmation' => 'MyOwnPass9',
    ])->assertRedirect(route('dashboard'));
    expect($user->fresh()->must_change_password)->toBeFalse();
});

test('people can sign in with e-mail, and disabled accounts cannot', function () {
    $user = User::factory()->create(['email' => 'tola@example.com', 'username' => 'tola', 'password' => bcrypt('Password1')]);

    $this->post('/login', ['email' => 'tola', 'password' => 'Password1'])->assertRedirect('/dashboard');
    auth()->logout();

    $user->forceFill(['is_disabled' => true])->save();
    $this->post('/login', ['email' => 'tola@example.com', 'password' => 'Password1'])->assertSessionHasErrors('email');
});

test('the mobile api signs in and returns the user', function () {
    $user = User::factory()->create(['email' => 'app@example.com', 'password' => bcrypt('Password1')]);
    $user->assignRole('Staff');

    $this->getJson('/api/v1/app/config')->assertOk()->assertJsonPath('data.name', config('qudroid.name'));

    $token = $this->postJson('/api/v1/auth/login', ['login' => 'app@example.com', 'password' => 'Password1', 'device_name' => 'Pixel'])
        ->assertOk()->assertJsonPath('data.user.user_type', 'staff')->json('data.token');

    $this->withToken($token)->getJson('/api/v1/auth/me')->assertOk()->assertJsonPath('data.email', 'app@example.com');
    $this->withToken($token)->getJson('/api/v1/notifications')->assertOk();
    $this->withToken($token)->getJson('/api/v1/me/profile')->assertOk();
    $this->withToken($token)->getJson('/api/v1/me/preferences')->assertOk();
});
