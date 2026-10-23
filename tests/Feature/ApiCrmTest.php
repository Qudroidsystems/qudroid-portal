<?php

use App\Models\Crm\Activity;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Services\Crm\CrmService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->rep = User::factory()->create(['user_type' => 'staff']);
    $this->rep->givePermissionTo(['View clients', 'Manage clients']);
    $this->viewer = User::factory()->create(['user_type' => 'staff']);
    $this->viewer->givePermissionTo('View clients');
    $this->nobody = User::factory()->create(['user_type' => 'staff']);
});

test('quick-add from the phone, then look things up', function () {
    Sanctum::actingAs($this->rep);
    $co = $this->postJson(route('api.v1.crm.companies.store'), ['name' => 'Ikeja Medics', 'industry' => 'Health', 'city' => 'Lagos'])->assertCreated()
        ->assertJsonPath('data.status', 'prospect')->json('data');
    $this->postJson(route('api.v1.crm.contacts.store'), ['name' => 'Dr Bisi Ade', 'company_id' => $co['id'], 'phone' => '0803', 'is_primary' => true])->assertCreated();
    app(CrmService::class)->saveDeal(['title' => 'Clinic website', 'company_id' => $co['id'], 'value' => 1500000], $this->rep);

    Sanctum::actingAs($this->viewer);
    $this->getJson(route('api.v1.crm.companies', ['q' => 'ikeja']))->assertOk()->assertJsonPath('data.0.open_deals', 1)
        ->assertJsonPath('data.0.open_deals_label', '₦1,500,000.00');
    $this->getJson(route('api.v1.crm.companies.show', $co['id']))->assertOk()->assertJsonPath('data.contacts.0.name', 'Dr Bisi Ade')
        ->assertJsonPath('data.deals.0.value', 150000000);
    $this->getJson(route('api.v1.crm.contacts', ['q' => 'bisi']))->assertJsonPath('data.0.company.name', 'Ikeja Medics');
    $this->postJson(route('api.v1.crm.companies.store'), ['name' => 'Nope'])->assertForbidden();

    Sanctum::actingAs($this->nobody);
    $this->getJson(route('api.v1.crm.companies'))->assertForbidden();
});

test('deals move across the board, are won or lost, and follow-ups are logged and done', function () {
    $crm = app(CrmService::class);
    $co = $crm->saveCompany(['name' => 'Acme', 'status' => 'prospect'], $this->rep);
    $a = $crm->saveDeal(['title' => 'A', 'company_id' => $co->id, 'value' => 100], $this->rep);
    $b = $crm->saveDeal(['title' => 'B', 'company_id' => $co->id, 'value' => 200, 'stage' => 'proposal'], $this->rep);
    Sanctum::actingAs($this->rep);

    $board = $this->getJson(route('api.v1.crm.deals', ['mine' => 1]))->assertOk();
    expect(collect($board->json('meta.stages'))->firstWhere('key', 'proposal')['count'])->toBe(1);

    $this->postJson(route('api.v1.crm.deals.move', $a), ['stage' => 'proposal', 'position' => 1])->assertOk()->assertJsonPath('data.stage', 'proposal');
    expect(Deal::where('stage', 'proposal')->orderBy('position')->pluck('title')->all())->toBe(['A', 'B']);
    $this->postJson(route('api.v1.crm.deals.move', $b), ['stage' => 'lost'])->assertStatus(422);
    $this->postJson(route('api.v1.crm.deals.move', $b), ['stage' => 'lost', 'lost_reason' => 'Budget'])->assertOk()->assertJsonPath('data.open', false);
    $this->postJson(route('api.v1.crm.deals.move', $a), ['stage' => 'won'])->assertOk()->assertJsonPath('data.stage', 'won');
    expect($co->fresh()->status)->toBe('client');

    $f = $this->postJson(route('api.v1.crm.activities.store'), ['subject_type' => 'deal', 'subject_id' => $a->id, 'type' => 'follow_up', 'body' => 'Send invoice', 'due_at' => today()->subDay()->toDateTimeString()])
        ->assertCreated()->assertJsonPath('data.subject.name', 'A')->json('data');
    $this->postJson(route('api.v1.crm.activities.store'), ['subject_type' => 'company', 'subject_id' => $co->id, 'type' => 'call', 'body' => 'Spoke to the MD'])->assertCreated();
    $this->getJson(route('api.v1.crm.activities', ['due' => 'overdue']))->assertJsonCount(1, 'data')->assertJsonPath('data.0.overdue', true);
    $this->postJson(route('api.v1.crm.activities.done', $f['id']))->assertOk();
    expect(Activity::find($f['id'])->done_at)->not->toBeNull();
    $this->getJson(route('api.v1.crm.deals.show', $a))->assertOk()->assertJsonPath('data.activities.0.type', 'follow_up');
});
