<?php

use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Crm\CrmService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function crmUser(string $role, string $type = 'staff'): User
{
    $u = User::factory()->create(['user_type' => $type]);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->sales = crmUser('Sales Officer');
    $this->pm = crmUser('Project Manager');      // View clients only
    $this->staff = crmUser('Staff');             // no CRM access
});

test('sales adds a company with contacts; others may only read', function () {
    $this->actingAs($this->sales)->post(route('crm.companies.store'), ['name' => 'Acme Ltd', 'status' => 'prospect', 'website' => 'acme.ng', 'industry' => 'Retail'])->assertRedirect();
    $company = Company::firstOrFail();
    expect($company->website)->toBe('https://acme.ng')->and($company->owner_id)->toBe($this->sales->id);

    $this->actingAs($this->sales)->post(route('crm.contacts.store'), ['company_id' => $company->id, 'name' => 'Ngozi Ade', 'email' => 'NGOZI@acme.ng', 'is_primary' => 1, 'back' => 1]);
    $this->actingAs($this->sales)->post(route('crm.contacts.store'), ['company_id' => $company->id, 'name' => 'Bola Ade', 'is_primary' => 1, 'back' => 1]);
    expect(Contact::where('is_primary', true)->pluck('name')->all())->toBe(['Bola Ade'])
        ->and(Contact::where('name', 'Ngozi Ade')->value('email'))->toBe('ngozi@acme.ng');

    $this->actingAs($this->pm)->get(route('crm.companies.show', $company))->assertOk()->assertSee('Ngozi Ade');
    $this->actingAs($this->pm)->get(route('crm.companies.create'))->assertForbidden();
    $this->actingAs($this->staff)->get(route('crm.index'))->assertForbidden();

    $this->actingAs($this->sales)->get(route('crm.companies.index', ['q' => 'Ngozi']))->assertOk()->assertSee('Acme Ltd');
    $this->actingAs($this->sales)->get(route('crm.contacts.index'))->assertOk()->assertSee('Bola Ade');
});

test('a contact gets a client portal account once', function () {
    $company = Company::create(['name' => 'Acme']);
    $contact = Contact::create(['company_id' => $company->id, 'name' => 'Ngozi', 'email' => 'ngozi@acme.ng']);

    $this->actingAs($this->sales)->post(route('crm.contacts.access', $contact))->assertSessionHas('client_password');
    $user = User::where('email', 'ngozi@acme.ng')->first();
    expect($user->isClient())->toBeTrue()->and($user->hasRole('Client'))->toBeTrue()->and($contact->fresh()->user_id)->toBe($user->id);
    $this->actingAs($this->sales)->post(route('crm.contacts.access', $contact))->assertSessionHasErrors('email');

    // A staff email cannot become a client account.
    $other = Contact::create(['name' => 'Staffer', 'email' => $this->staff->email]);
    $this->actingAs($this->sales)->post(route('crm.contacts.access', $other))->assertSessionHasErrors('email');
});

test('deals move through the pipeline, are won or lost, and log each step', function () {
    $company = Company::create(['name' => 'Acme', 'status' => 'prospect']);
    $this->actingAs($this->sales)->post(route('crm.deals.store'), ['title' => 'Website', 'company_id' => $company->id, 'value' => 2500000])->assertRedirect();
    $this->actingAs($this->sales)->post(route('crm.deals.store'), ['title' => 'App', 'value' => 1000000, 'stage' => 'qualified', 'back' => 1]);
    $deal = Deal::where('title', 'Website')->first();
    $app = Deal::where('title', 'App')->first();
    expect($deal->stage)->toBe('new')->and($deal->probability)->toBe(10)->and($app->probability)->toBe(40);

    $this->actingAs($this->sales)->postJson(route('crm.deals.move', $deal), ['stage' => 'qualified', 'order' => [$deal->id, $app->id]])->assertOk();
    expect($deal->fresh()->stage)->toBe('qualified')->and($deal->fresh()->probability)->toBe(40)
        ->and($deal->fresh()->position)->toBe(1)->and($app->fresh()->position)->toBe(2);
    $this->actingAs($this->sales)->postJson(route('crm.deals.move', $deal), ['stage' => 'won'])->assertStatus(422);
    $this->actingAs($this->pm)->postJson(route('crm.deals.move', $deal), ['stage' => 'proposal'])->assertForbidden();

    $stats = app(CrmService::class)->stats();
    expect($stats['openValue'])->toBe(350000000)->and($stats['weighted'])->toBe(140000000);

    $this->actingAs($this->sales)->post(route('crm.deals.win', $deal))->assertSessionHasNoErrors();
    expect($deal->fresh()->stage)->toBe('won')->and($company->fresh()->status)->toBe('client');

    $this->actingAs($this->sales)->post(route('crm.deals.lose', $app), ['reason' => ''])->assertSessionHasErrors('reason');
    $this->actingAs($this->sales)->post(route('crm.deals.lose', $app), ['reason' => 'Budget cut'])->assertSessionHasNoErrors();
    expect($app->fresh()->lost_reason)->toBe('Budget cut')
        ->and(Activity::where('type', 'stage')->where('subject_id', $deal->id)->count())->toBe(2)
        ->and(app(CrmService::class)->stats()['winRate'])->toBe(50);

    $this->actingAs($this->sales)->post(route('crm.deals.reopen', $app));
    expect($app->fresh()->isOpen())->toBeTrue();

    $this->actingAs($this->sales)->get(route('crm.deals.index'))->assertOk()->assertSee('App')->assertDontSee('Website</div>', false);
    $this->actingAs($this->sales)->get(route('crm.deals.index', ['view' => 'list']))->assertOk()->assertSee('Website');
    // Only people who can create projects get the link.
    $this->actingAs($this->sales)->get(route('crm.deals.show', $deal))->assertOk()->assertSee('Won on')->assertDontSee('Start a project from this deal');
    $this->actingAs($this->pm)->get(route('crm.deals.show', $deal))->assertOk()->assertSee('Start a project from this deal');
});

test('a won deal becomes a project linked to the company', function () {
    $company = Company::create(['name' => 'Acme', 'status' => 'client']);
    $deal = app(CrmService::class)->saveDeal(['title' => 'Website rebuild', 'company_id' => $company->id, 'value' => 500000], $this->sales);
    app(CrmService::class)->win($deal, $this->sales);

    $this->actingAs($this->pm)->get(route('projects.create', ['deal' => $deal->id]))->assertOk()->assertSee('Website rebuild')->assertSee('500000');
    $this->actingAs($this->pm)->post(route('projects.store'), ['code' => 'ACME', 'name' => 'Website rebuild', 'priority' => 'normal', 'deal_id' => $deal->id])->assertRedirect();
    $project = Project::where('code', 'ACME')->first();
    expect($project->company_id)->toBe($company->id)->and($deal->fresh()->project_id)->toBe($project->id);

    $this->actingAs($this->pm)->get(route('crm.companies.show', $company))->assertOk()->assertSee('Website rebuild');
    $this->actingAs($this->pm)->get(route('projects.show', $project))->assertOk()->assertSee('Acme');
});

test('follow-ups are assigned, listed and ticked off', function () {
    $company = Company::create(['name' => 'Acme']);
    $this->actingAs($this->pm)->post(route('crm.activities.store', ['company', $company->id]), ['type' => 'call', 'body' => 'Spoke to the MD'])->assertSessionHasNoErrors();
    $this->actingAs($this->pm)->post(route('crm.activities.store', ['company', $company->id]), ['type' => 'follow_up', 'body' => 'Send proposal'])->assertSessionHasErrors('due_at');
    $this->actingAs($this->pm)->post(route('crm.activities.store', ['company', $company->id]), [
        'type' => 'follow_up', 'body' => 'Send proposal', 'due_at' => now()->subHour()->format('Y-m-d\TH:i'), 'assigned_to' => $this->sales->id,
    ])->assertSessionHasNoErrors();

    $fu = Activity::where('type', 'follow_up')->first();
    expect($fu->assigned_to)->toBe($this->sales->id)->and($fu->isOverdue())->toBeTrue()
        ->and($this->sales->notifications()->count())->toBe(1);

    $this->actingAs($this->sales)->get(route('crm.index'))->assertOk()->assertSee('Send proposal');
    $this->actingAs($this->sales)->get(route('crm.follow-ups'))->assertOk()->assertSee('Send proposal');
    $this->actingAs($this->sales)->post(route('crm.activities.complete', $fu));
    expect($fu->fresh()->done_at)->not->toBeNull();
    $this->actingAs($this->sales)->get(route('crm.follow-ups'))->assertDontSee('Send proposal');

    // Stage entries cannot be deleted; a note can be deleted by its author.
    $note = Activity::where('type', 'call')->first();
    $this->actingAs($this->staff)->delete(route('crm.activities.destroy', $note))->assertForbidden();
    $this->actingAs($this->pm)->delete(route('crm.activities.destroy', $note))->assertRedirect();
    expect(Activity::where('type', 'call')->exists())->toBeFalse();
});
