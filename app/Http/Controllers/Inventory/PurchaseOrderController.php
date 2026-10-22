<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Finance\Account;
use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Services\Inventory\PurchaseOrderService;
use Illuminate\Http\Request;

class PurchaseOrderController extends Controller
{
    public function __construct(protected PurchaseOrderService $orders) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'open');
        $q = PurchaseOrder::with('supplier')
            ->when($status === 'open', fn ($w) => $w->whereIn('status', ['draft', 'ordered', 'partial']))
            ->when($status !== 'open' && $status !== 'all', fn ($w) => $w->where('status', $status))
            ->when($request->filled('supplier'), fn ($w) => $w->where('supplier_id', $request->supplier));

        return view('modules.inventory.orders.index', [
            'orders' => $q->latest('id')->paginate(30)->withQueryString(),
            'suppliers' => Supplier::orderBy('name')->get(['id', 'name']),
            'status' => $status,
        ]);
    }

    public function create(Request $request)
    {
        $po = new PurchaseOrder(['supplier_id' => $request->integer('supplier') ?: null, 'location_id' => Location::value('id')]);
        $lines = [];
        if ($item = Item::where('sku', $request->input('item'))->first()) {
            $po->supplier_id ??= $item->supplier_id;
            $lines[] = ['item_id' => $item->id, 'description' => $item->name, 'quantity' => max(1, $item->reorder_level * 2), 'unit_cost' => (float) $item->avg_cost];
        }

        return view('modules.inventory.orders.form', $this->formData($po, $lines));
    }

    public function store(Request $request)
    {
        $po = $this->orders->save($this->validated($request), $request->input('lines', []), $request->user());

        return redirect()->route('inventory.orders.show', $po)->with('success', 'Purchase order ' . $po->number . ' saved as a draft.');
    }

    public function show(PurchaseOrder $order)
    {
        $order->load('supplier', 'location', 'lines.item', 'creator', 'expense');

        return view('modules.inventory.orders.show', [
            'po' => $order,
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'expenseAccounts' => Account::ofType('expense')->where('is_active', true)->orderBy('code')->get(),
        ]);
    }

    public function edit(PurchaseOrder $order)
    {
        abort_unless($order->status === 'draft', 404);
        $lines = $order->lines->map(fn ($l) => ['item_id' => $l->item_id, 'description' => $l->description, 'quantity' => $l->quantity, 'unit_cost' => (float) $l->unit_cost])->all();

        return view('modules.inventory.orders.form', $this->formData($order, $lines));
    }

    public function update(Request $request, PurchaseOrder $order)
    {
        $this->orders->save($this->validated($request), $request->input('lines', []), $request->user(), $order);

        return redirect()->route('inventory.orders.show', $order)->with('success', 'Saved.');
    }

    public function place(PurchaseOrder $order)
    {
        $this->orders->place($order);

        return back()->with('success', 'Order placed. Receive the goods here when they arrive.');
    }

    public function receive(Request $request, PurchaseOrder $order)
    {
        $d = $request->validate(['location_id' => ['required', 'exists:inv_locations,id'], 'received' => ['array'], 'received.*' => ['nullable', 'numeric', 'min:0'],
            'reference' => ['nullable', 'string', 'max:100']]);
        $order = $this->orders->receive($order, $d['received'] ?? [], Location::find($d['location_id']), $request->user(), $d['reference'] ?? null);

        return back()->with('success', $order->status === 'received' ? 'Everything on this order has arrived.' : 'Delivery recorded. Some items are still to come.');
    }

    public function cancel(PurchaseOrder $order)
    {
        $this->orders->cancel($order);

        return back()->with('success', 'Order cancelled.');
    }

    public function bill(Request $request, PurchaseOrder $order)
    {
        $d = $request->validate(['account_id' => ['required', 'exists:finance_accounts,id'], 'invoice' => ['nullable', 'file', 'max:10240', 'mimes:pdf,jpg,jpeg,png']]);
        $expense = $this->orders->bill($order->load('lines', 'supplier'), $request->user(), (int) $d['account_id'], $request->file('invoice'));

        return back()->with('success', 'Sent to Finance as expense ' . $expense->number . ' for approval.');
    }

    protected function formData(PurchaseOrder $po, array $lines): array
    {
        return [
            'po' => $po,
            'lines' => $lines ?: [['item_id' => null, 'description' => '', 'quantity' => 1, 'unit_cost' => '']],
            'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'items' => Item::where('is_active', true)->orderBy('name')->get(['id', 'sku', 'name', 'unit', 'avg_cost']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
        ];
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'supplier_id' => ['required', 'exists:inv_suppliers,id'],
            'expected_on' => ['nullable', 'date'],
            'location_id' => ['nullable', 'exists:inv_locations,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'lines' => ['required', 'array', 'max:100'],
        ]);
    }
}
