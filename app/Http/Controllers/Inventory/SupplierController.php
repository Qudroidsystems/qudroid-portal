<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Inventory\Supplier;
use App\Support\Money;
use Illuminate\Http\Request;

class SupplierController extends Controller
{
    public function index(Request $request)
    {
        $suppliers = Supplier::withCount(['purchaseOrders as open_orders_count' => fn ($q) => $q->whereIn('status', ['ordered', 'partial'])])
            ->when($request->filled('q'), fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $request->q . '%')->orWhere('contact_name', 'like', '%' . $request->q . '%')))
            ->where('is_active', $request->input('show') !== 'archived')
            ->orderBy('name')->get();

        return view('modules.inventory.suppliers.index', ['suppliers' => $suppliers]);
    }

    public function create()
    {
        return view('modules.inventory.suppliers.form', ['supplier' => new Supplier(['is_active' => true])]);
    }

    public function store(Request $request)
    {
        $supplier = Supplier::create($this->validated($request) + ['is_active' => true]);

        return redirect()->route('inventory.suppliers.show', $supplier)->with('success', $supplier->name . ' added.');
    }

    public function show(Supplier $supplier)
    {
        $orders = $supplier->purchaseOrders()->latest('id')->get();

        return view('modules.inventory.suppliers.show', [
            'supplier' => $supplier,
            'orders' => $orders,
            'spent' => $orders->whereIn('status', ['partial', 'received'])->sum(fn (PurchaseOrder $o) => Money::minor($o->total)),
            'items' => $supplier->items()->orderBy('name')->get(),
            'assets' => $supplier->assets()->orderBy('tag')->get(),
        ]);
    }

    public function edit(Supplier $supplier)
    {
        return view('modules.inventory.suppliers.form', ['supplier' => $supplier]);
    }

    public function update(Request $request, Supplier $supplier)
    {
        $supplier->update($this->validated($request) + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('inventory.suppliers.show', $supplier)->with('success', 'Saved.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'],
            'address' => ['nullable', 'string', 'max:255'],
            'bank_details' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ]);
    }
}
