<?php

namespace App\Services\Inventory;

use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\PurchaseOrder;
use App\Models\User;
use App\Services\Finance\DocumentNumber;
use App\Services\Finance\ExpenseService;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Buying from suppliers: a draft order is placed (ordered), goods are
 * received against it, possibly in several deliveries, which adds stock
 * items to the store; then the supplier's bill is sent to Finance as an
 * expense for approval and payment.
 */
class PurchaseOrderService
{
    public function __construct(protected StockService $stock) {}

    /**
     * @param  array<int, array{item_id?:mixed, description?:string, quantity:mixed, unit_cost:mixed}>  $lines
     */
    public function save(array $data, array $lines, User $by, ?PurchaseOrder $po = null): PurchaseOrder
    {
        $this->check($po && $po->status !== 'draft', 'status', 'Only a draft order can be changed.');
        $rows = [];
        foreach (array_values($lines) as $i => $l) {
            $item = !empty($l['item_id']) ? Item::find($l['item_id']) : null;
            $description = trim((string) ($l['description'] ?? '')) ?: $item?->name;
            if (!$description && empty($l['quantity'])) continue;
            $qty = round((float) ($l['quantity'] ?? 0), 2);
            $cost = Money::minor($l['unit_cost'] ?? 0);
            $this->check(!$description || $qty <= 0 || $cost < 0, 'lines', 'Line ' . ($i + 1) . ': check the item, quantity and price.');
            $rows[] = ['item_id' => $item?->id, 'description' => mb_substr($description, 0, 255), 'quantity' => $qty, 'unit_cost' => Money::major($cost), 'position' => $i];
        }
        $this->check(!$rows, 'lines', 'Add at least one line.');
        $total = array_sum(array_map(fn ($r) => (int) round($r['quantity'] * Money::minor($r['unit_cost'])), $rows));

        return DB::transaction(function () use ($data, $rows, $total, $by, $po) {
            $fields = [
                'supplier_id' => $data['supplier_id'], 'expected_on' => $data['expected_on'] ?? null, 'location_id' => $data['location_id'] ?? null,
                'notes' => $data['notes'] ?? null, 'total' => Money::major($total),
                'currency' => strtoupper($data['currency'] ?? config('qudroid.academy.currency', 'NGN')),
            ];
            if ($po) {
                $po->update($fields);
                $po->lines()->delete();
            } else {
                $po = PurchaseOrder::create($fields + ['number' => DocumentNumber::next('purchase_order'), 'status' => 'draft', 'created_by' => $by->id]);
            }
            $po->lines()->createMany($rows);

            return $po;
        });
    }

    public function place(PurchaseOrder $po): PurchaseOrder
    {
        $this->check($po->status !== 'draft', 'status', 'This order has already been placed.');
        $po->update(['status' => 'ordered', 'order_date' => $po->order_date ?? today()]);

        return $po;
    }

    /**
     * Record a delivery.
     *
     * @param  array<int|string, mixed>  $quantities  line id => quantity received now
     */
    public function receive(PurchaseOrder $po, array $quantities, Location $into, User $by, ?string $reference = null): PurchaseOrder
    {
        $this->check(!in_array($po->status, ['ordered', 'partial'], true), 'status', 'Place the order before receiving against it.');
        $any = false;

        DB::transaction(function () use ($po, $quantities, $into, $by, $reference, &$any) {
            foreach ($po->lines()->lockForUpdate()->get() as $line) {
                $qty = round((float) ($quantities[$line->id] ?? 0), 2);
                if ($qty <= 0) continue;
                $this->check($qty > $line->outstanding(), "received.{$line->id}", $line->description . ': only ' . Item::qty($line->outstanding()) . ' still to come.');
                if ($line->item) {
                    $this->stock->receive($line->item, $into, $qty, $line->unit_cost, $by, [
                        'purchase_order_id' => $po->id, 'reference' => $reference ?: $po->number, 'note' => $po->supplier->name,
                    ]);
                }
                $line->update(['received' => round($line->received + $qty, 2)]);
                $any = true;
            }
            $this->check(!$any, 'received', 'Enter what arrived.');
            $done = $po->lines()->get()->every(fn ($l) => $l->outstanding() <= 0);
            $po->update(['status' => $done ? 'received' : 'partial']);
        });

        return $po->fresh();
    }

    public function cancel(PurchaseOrder $po): PurchaseOrder
    {
        $this->check(!in_array($po->status, ['draft', 'ordered'], true), 'status', 'Goods have arrived against this order, so it can\'t be cancelled. Close it by receiving what came.');
        $po->update(['status' => 'cancelled', 'cancelled_at' => now()]);

        return $po;
    }

    /** Send what was received to Finance as an expense to approve and pay. */
    public function bill(PurchaseOrder $po, User $by, int $accountId, ?\Illuminate\Http\UploadedFile $invoice = null)
    {
        $this->check(!in_array($po->status, ['partial', 'received'], true), 'status', 'Nothing has been received on this order yet.');
        $this->check($po->expense_id !== null, 'status', 'This order has already been sent to Finance.');
        $amount = $po->lines->sum(fn ($l) => (int) round($l->received * Money::minor($l->unit_cost)));
        $this->check($amount <= 0, 'status', 'The received goods have no cost.');

        return DB::transaction(function () use ($po, $by, $accountId, $invoice, $amount) {
            $expense = app(ExpenseService::class)->submit([
                'date' => today()->toDateString(), 'payee' => $po->supplier->name, 'account_id' => $accountId,
                'description' => 'Purchase order ' . $po->number . ': ' . $po->lines->pluck('description')->take(3)->implode(', ') . ($po->lines->count() > 3 ? ' …' : ''),
                'amount' => Money::major($amount), 'currency' => $po->currency,
            ], $by, $invoice);
            $po->update(['expense_id' => $expense->id]);

            return $expense;
        });
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
