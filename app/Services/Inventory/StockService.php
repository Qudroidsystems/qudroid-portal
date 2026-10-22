<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\Movement;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Stock;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use App\Support\Money;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Stock levels. Quantities only change through a movement (received,
 * issued, adjusted after a count, transferred between locations), so
 * every number on screen can be traced. Receiving updates the item's
 * weighted average cost. Falling to the reorder level tells the
 * inventory team, once a day.
 */
class StockService
{
    public function receive(Item $item, Location $at, float $qty, $unitCost, User $by, array $extra = []): Movement
    {
        $this->check($qty <= 0, 'quantity', 'Enter how many arrived.');
        $cost = Money::minor($unitCost ?? 0);
        $this->check($cost < 0, 'unit_cost', 'The cost can\'t be negative.');

        return DB::transaction(function () use ($item, $at, $qty, $cost, $by, $extra) {
            $item = Item::whereKey($item->id)->lockForUpdate()->first();
            $onHand = (float) Stock::where('item_id', $item->id)->sum('quantity');
            $avg = Money::minor($item->avg_cost);
            $newAvg = $onHand + $qty > 0 ? (int) round((max(0, $onHand) * $avg + $qty * $cost) / (max(0, $onHand) + $qty)) : $cost;
            $item->update(['avg_cost' => Money::major($newAvg)]);

            return $this->move($item, $at, $qty, 'receive', $by, ['unit_cost' => Money::major($cost)] + $extra);
        });
    }

    public function issue(Item $item, Location $from, float $qty, User $by, array $extra = []): Movement
    {
        $this->check($qty <= 0, 'quantity', 'Enter how many to issue.');

        return $this->watchLevel($item, fn () => DB::transaction(fn () => $this->move($item, $from, -$qty, 'issue', $by,
            ['unit_cost' => $item->avg_cost] + $extra)));
    }

    /** Set the count at a location to what was physically counted. */
    public function adjust(Item $item, Location $at, float $counted, User $by, string $reason): ?Movement
    {
        $this->check($counted < 0, 'quantity', 'The count can\'t be negative.');
        $this->check(trim($reason) === '', 'note', 'Say why the count changed.');

        return $this->watchLevel($item, fn () => DB::transaction(function () use ($item, $at, $counted, $by, $reason) {
            $current = (float) (Stock::where('item_id', $item->id)->where('location_id', $at->id)->lockForUpdate()->value('quantity') ?? 0);
            $diff = round($counted - $current, 2);
            if ($diff == 0) return null;

            return $this->move($item, $at, $diff, 'adjust', $by, ['unit_cost' => $item->avg_cost, 'note' => mb_substr(trim($reason), 0, 255)]);
        }));
    }

    public function transfer(Item $item, Location $from, Location $to, float $qty, User $by, ?string $note = null): void
    {
        $this->check($qty <= 0, 'quantity', 'Enter how many to move.');
        $this->check($from->id === $to->id, 'to_location_id', 'Choose a different location to move to.');

        DB::transaction(function () use ($item, $from, $to, $qty, $by, $note) {
            $extra = ['unit_cost' => $item->avg_cost, 'note' => $note];
            $this->move($item, $from, -$qty, 'transfer', $by, $extra + ['reference' => 'To ' . $to->name]);
            $this->move($item, $to, $qty, 'transfer', $by, $extra + ['reference' => 'From ' . $from->name]);
        });
    }

    /** Active items at or below their reorder level, with `on_hand`. */
    public function lowStock(): Collection
    {
        return $this->withOnHand(Item::with('supplier')->where('is_active', true)->where('reorder_level', '>', 0))->get()
            ->filter(fn (Item $i) => $i->onHand() <= $i->reorder_level)->values();
    }

    /** Adds an `on_hand` column (units across all locations). */
    public function withOnHand($query)
    {
        return $query->select('inv_items.*')
            ->addSelect(['on_hand' => Stock::selectRaw('coalesce(sum(quantity), 0)')->whereColumn('item_id', 'inv_items.id')]);
    }

    /** Total stock value at average cost, in minor units. */
    public function valueMinor(): int
    {
        return (int) round(DB::table('inv_stock')->join('inv_items', 'inv_items.id', '=', 'inv_stock.item_id')
            ->where('inv_stock.quantity', '>', 0)->selectRaw('sum(inv_stock.quantity * inv_items.avg_cost) v')->value('v') * 100);
    }

    protected function move(Item $item, Location $at, float $qty, string $type, User $by, array $extra = []): Movement
    {
        $this->check(!$at->is_active && $qty > 0, 'location_id', $at->name . ' is closed.');
        $stock = Stock::firstOrCreate(['item_id' => $item->id, 'location_id' => $at->id], ['quantity' => 0]);
        $stock = Stock::whereKey($stock->id)->lockForUpdate()->first();
        $balance = round($stock->quantity + $qty, 2);
        $this->check($balance < 0, 'quantity', 'Only ' . Item::qty($stock->quantity) . ' ' . $item->unit . ' of ' . $item->name . ' at ' . $at->name . '.');
        $stock->update(['quantity' => $balance]);

        return Movement::create([
            'item_id' => $item->id, 'location_id' => $at->id, 'type' => $type, 'quantity' => $qty, 'balance' => $balance,
            'created_by' => $by->id,
        ] + collect($extra)->only(['unit_cost', 'purchase_order_id', 'issued_to', 'project_id', 'reference', 'note'])
            ->map(fn ($v) => is_string($v) ? (trim($v) === '' ? null : mb_substr(trim($v), 0, 255)) : $v)->all());
    }

    /** Run a stock-reducing change and tell the team if it takes the item to its reorder level. */
    protected function watchLevel(Item $item, callable $change)
    {
        $before = (float) Stock::where('item_id', $item->id)->sum('quantity');
        $result = $change();
        $after = (float) Stock::where('item_id', $item->id)->sum('quantity');

        if ($item->reorder_level > 0 && $after <= $item->reorder_level && $before > $item->reorder_level) {
            PortalNotifier::toUsers(PortalNotifier::userIdsWithPermission('Manage inventory'), 'Low stock: ' . $item->name,
                Item::qty($after) . ' ' . $item->unit . ' left (reorder at ' . Item::qty($item->reorder_level) . ').',
                route('inventory.items.show', $item), 'inventory', 'inv-low:' . $item->id . ':' . today()->toDateString());
        }

        return $result;
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
