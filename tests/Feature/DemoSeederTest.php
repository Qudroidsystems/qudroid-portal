<?php

use App\Models\Academy\Enrollment;
use App\Models\Finance\JournalLine;
use App\Models\Projects\Task;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('the demo seeder fills every module once, with books that balance', function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoSeeder::class);

    $md = User::where('email', 'adaeze.okafor@' . DemoSeeder::DOMAIN)->first();
    expect($md->hasRole('Managing Director'))->toBeTrue()
        ->and(Enrollment::count())->toBe(12)
        ->and(Task::count())->toBeGreaterThan(10)
        ->and((float) JournalLine::sum('debit'))->toBe((float) JournalLine::sum('credit'));

    $people = User::count();
    $this->seed(DemoSeeder::class);   // a second run adds nothing
    expect(User::count())->toBe($people);

    $this->post(route('login'), ['email' => $md->email, 'password' => DemoSeeder::PASSWORD]);
    $this->assertAuthenticatedAs($md);
    foreach (['dashboard', 'checkin.index', 'projects.index', 'finance.invoices.index', 'hr.employees.index', 'crm.companies.index'] as $route) {
        if (Route::has($route)) $this->get(route($route))->assertOk();
    }
});
