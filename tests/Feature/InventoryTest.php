<?php

use App\Models\Finance\Account;
use App\Models\Finance\Expense;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\Movement;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Models\User;
use App\Services\Inventory\StockService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

function invUser(string $role): User
{
    $u = User::factory()->create(['user_type' => 'staff']);
    $u->assignRole($role);
    $u->forceFill(['must_change_password' => false])->save();

    return $u;
}

beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->store = invUser('Inventory Officer');
    $this->md = invUser('Managing Director');     // View inventory only
    $this->staff = invUser('Staff');
    $this->main = Location::firstOrFail();
    $this->supplier = Supplier::create(['name' => 'Office Hub Ltd', 'is_active' => true]);
});

test('stock is received at average cost, issued, counted and moved, with a full history', function () {
    $this->actingAs($this->store)->post(route('inventory.items.store'), [
        'name' => 'A4 paper', 'unit' => 'ream', 'reorder_level' => 5, 'opening_quantity' => 10, 'opening_cost' => 4000, 'opening_location_id' => $this->main->id,
    ])->assertRedirect();
    $item = Item::firstOrFail();
    expect($item->sku)->toBe('A4-PAP');

    $this->actingAs($this->store)->post(route('inventory.items.receive', $item), ['location_id' => $this->main->id, 'quantity' => 10, 'unit_cost' => 5000])->assertSessionHasNoErrors();
    expect((float) $item->fresh()->avg_cost)->toBe(4500.0);

    // can't issue more than is there
    $this->actingAs($this->store)->post(route('inventory.items.issue', $item), ['location_id' => $this->main->id, 'quantity' => 25])->assertSessionHasErrors('quantity');
    $this->actingAs($this->store)->post(route('inventory.items.issue', $item), ['location_id' => $this->main->id, 'quantity' => 6, 'issued_to' => $this->staff->id])->assertSessionHasNoErrors();

    $annex = Location::create(['name' => 'Training centre', 'is_active' => true]);
    $this->actingAs($this->store)->post(route('inventory.items.transfer', $item), ['location_id' => $this->main->id, 'to_location_id' => $annex->id, 'quantity' => 4]);
    // count found 9 at the main store (10 expected): falls to the reorder level and warns the team once
    $this->actingAs($this->store)->post(route('inventory.items.adjust', $item), ['location_id' => $this->main->id, 'quantity' => 1, 'note' => 'Stock count'])->assertSessionHasNoErrors();

    expect($item->fresh()->onHand())->toBe(5.0)
        ->and($item->fresh()->isLow())->toBeTrue()
        ->and(Movement::where('item_id', $item->id)->count())->toBe(6)
        ->and($this->store->notifications()->count())->toBe(1)
        ->and(app(StockService::class)->valueMinor())->toBe(5 * 450000);

    $this->actingAs($this->md)->get(route('inventory.items.show', $item))->assertOk()->assertSee('Stock count')->assertDontSee('Update stock');
    $this->actingAs($this->md)->post(route('inventory.items.issue', $item), ['location_id' => $this->main->id, 'quantity' => 1])->assertForbidden();
    $this->actingAs($this->staff)->get(route('inventory.index'))->assertForbidden();
    $this->actingAs($this->md)->get(route('inventory.index'))->assertOk()->assertSee('A4 paper');
});

test('a purchase order is placed, received in two deliveries and billed to Finance', function () {
    $item = Item::create(['sku' => 'TONER', 'name' => 'Toner cartridge', 'unit' => 'pcs', 'supplier_id' => $this->supplier->id]);

    $this->actingAs($this->store)->post(route('inventory.orders.store'), [
        'supplier_id' => $this->supplier->id, 'location_id' => $this->main->id,
        'lines' => [
            ['item_id' => $item->id, 'description' => '', 'quantity' => 10, 'unit_cost' => 25000],
            ['item_id' => '', 'description' => 'Delivery', 'quantity' => 1, 'unit_cost' => 5000],
        ],
    ])->assertRedirect();
    $po = PurchaseOrder::firstOrFail();
    expect($po->number)->toContain('PO-')->and((float) $po->total)->toBe(255000.0)->and($po->lines[0]->description)->toBe('Toner cartridge');

    $this->actingAs($this->store)->post(route('inventory.orders.receive', $po), ['location_id' => $this->main->id, 'received' => [$po->lines[0]->id => 4]])->assertSessionHasErrors('status');
    $this->actingAs($this->store)->post(route('inventory.orders.place', $po));
    [$toner, $delivery] = [$po->lines[0], $po->lines[1]];

    $this->actingAs($this->store)->post(route('inventory.orders.receive', $po), ['location_id' => $this->main->id, 'received' => [$toner->id => 4]])->assertSessionHasNoErrors();
    expect($po->fresh()->status)->toBe('partial')->and($item->fresh()->onHand())->toBe(4.0);
    $this->actingAs($this->store)->post(route('inventory.orders.receive', $po), ['location_id' => $this->main->id, 'received' => [$toner->id => 7]])->assertSessionHasErrors("received.{$toner->id}");
    $this->actingAs($this->store)->post(route('inventory.orders.receive', $po), ['location_id' => $this->main->id, 'received' => [$toner->id => 6, $delivery->id => 1]])->assertSessionHasNoErrors();
    expect($po->fresh()->status)->toBe('received')->and($item->fresh()->onHand())->toBe(10.0)->and((float) $item->fresh()->avg_cost)->toBe(25000.0);

    $this->actingAs($this->store)->post(route('inventory.orders.cancel', $po))->assertSessionHasErrors('status');

    $account = Account::where('type', 'expense')->firstOrFail();
    $this->actingAs($this->store)->post(route('inventory.orders.bill', $po), ['account_id' => $account->id])->assertSessionHasNoErrors();
    $expense = Expense::firstOrFail();
    expect($expense->status)->toBe('submitted')->and((float) $expense->amount)->toBe(255000.0)->and($expense->payee)->toBe('Office Hub Ltd')
        ->and($po->fresh()->expense_id)->toBe($expense->id);
    $this->actingAs($this->store)->post(route('inventory.orders.bill', $po), ['account_id' => $account->id])->assertSessionHasErrors('status');

    $this->actingAs($this->store)->get(route('inventory.orders.show', $po))->assertOk()->assertSee($expense->number);
    $this->actingAs($this->store)->get(route('inventory.orders.create', ['item' => 'TONER']))->assertOk()->assertSee('Toner cartridge');
});

test('assets are tagged, handed over, returned, repaired and retired with a history', function () {
    $this->actingAs($this->store)->post(route('inventory.assets.store'), [
        'name' => 'MacBook Air', 'serial_number' => 'C02XYZ', 'condition' => 'new', 'purchase_cost' => 1500000, 'supplier_id' => $this->supplier->id,
    ])->assertRedirect();
    $asset = Asset::firstOrFail();
    expect($asset->tag)->toBe('QUD-AST-0001')->and($asset->status)->toBe('in_store');

    $act = fn (array $d) => $this->actingAs($this->store)->post(route('inventory.assets.action', $asset), $d);
    $act(['do' => 'assign', 'user_id' => $this->staff->id, 'note' => 'With charger'])->assertSessionHasNoErrors();
    expect($asset->fresh()->assigned_to)->toBe($this->staff->id)->and($this->staff->notifications()->count())->toBe(1);
    $act(['do' => 'assign', 'user_id' => $this->md->id])->assertSessionHasErrors('user_id');

    $this->actingAs($this->staff)->get(route('me.assets'))->assertOk()->assertSee('MacBook Air')->assertSee('QUD-AST-0001');

    $act(['do' => 'repair', 'note' => 'Screen flicker'])->assertSessionHasNoErrors();
    expect($asset->fresh()->status)->toBe('repair')->and($asset->fresh()->assigned_to)->toBeNull();
    $act(['do' => 'repaired'])->assertSessionHasNoErrors();
    $act(['do' => 'retire'])->assertSessionHasErrors('note');
    $act(['do' => 'retire', 'note' => 'Sold to staff'])->assertSessionHasNoErrors();

    expect($asset->fresh()->status)->toBe('retired')
        ->and($asset->events()->pluck('type')->sort()->values()->all())->toBe(['added', 'assigned', 'repair', 'repaired', 'retired', 'returned']);
    $this->actingAs($this->md)->get(route('inventory.assets.show', $asset))->assertOk()->assertSee('Sold to staff')->assertDontSee('Hand over');
});

test('the inventory screens render', function () {
    $item = Item::create(['sku' => 'PEN', 'name' => 'Pens', 'unit' => 'box']);
    $asset = Asset::create(['tag' => 'QUD-AST-0009', 'name' => 'Router', 'status' => 'in_store', 'condition' => 'good']);
    foreach ([route('inventory.index'), route('inventory.items.index'), route('inventory.items.create'), route('inventory.items.show', $item),
              route('inventory.items.edit', $item), route('inventory.movements'), route('inventory.suppliers.index'), route('inventory.suppliers.create'),
              route('inventory.suppliers.show', $this->supplier), route('inventory.orders.index'), route('inventory.orders.create'),
              route('inventory.assets.index'), route('inventory.assets.create'), route('inventory.assets.show', $asset), route('inventory.settings')] as $url) {
        $this->actingAs($this->store)->get($url)->assertOk();
    }
});
