<?php

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;

uses(RefreshDatabase::class);

// Every signed-in page without a parameter opens without a server error, for the Super Administrator.
test('every simple page opens', function () {
    $this->seed(DatabaseSeeder::class);
    $admin = User::whereHas('roles', fn ($q) => $q->where('name', 'Super Administrator'))->first()
        ?? tap(User::factory()->create(['user_type' => 'staff']))->assignRole('Super Administrator');
    $admin->forceFill(['must_change_password' => false])->save();

    $skip = ['logout', 'sanctum.csrf-cookie', 'storage.local', 'password.confirm'];   // password.confirm uses the unbuilt Vite layout
    $failed = [];
    foreach (Route::getRoutes() as $route) {
        if (!in_array('GET', $route->methods(), true) || $route->parameterNames() || !$route->getName()) continue;
        if (in_array($route->getName(), $skip, true) || str_starts_with($route->uri(), 'api/') || str_starts_with($route->uri(), '_')) continue;
        $res = $this->actingAs($admin)->get('/' . ltrim($route->uri(), '/'));
        if ($res->getStatusCode() >= 500) $failed[] = $route->getName() . ' → ' . $res->getStatusCode() . ' ' . \Illuminate\Support\Str::limit(strip_tags((string) ($res->exception?->getMessage() ?? '')), 150);
    }
    expect($failed)->toBe([]);
});
