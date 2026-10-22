<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\Supplier;
use App\Models\Projects\Project;
use App\Models\User;
use App\Services\Inventory\StockService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ItemController extends Controller
{
    public function __construct(protected StockService $stock) {}

    public function index(Request $request)
    {
        $q = $this->stock->withOnHand(Item::with('category'))
            ->when($request->filled('q'), fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $request->q . '%')->orWhere('sku', 'like', '%' . $request->q . '%')))
            ->when($request->filled('category'), fn ($w) => $w->where('category_id', $request->category))
            ->where('is_active', $request->input('show') !== 'archived');
        $items = $q->orderBy('name')->get();
        if ($request->input('show') === 'low') {
            $items = $items->filter(fn ($i) => $i->isLow())->values();
        }

        return view('modules.inventory.items.index', [
            'items' => $items,
            'categories' => Category::kind('stock')->get(),
        ]);
    }

    public function create()
    {
        return view('modules.inventory.items.form', $this->formData(new Item(['unit' => 'pcs', 'is_active' => true])));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        $data['sku'] = strtoupper(trim($data['sku'] ?? '')) ?: $this->suggestSku($data['name']);
        $request->merge(['sku' => $data['sku']])->validate(['sku' => [Rule::unique('inv_items', 'sku')]]);
        $item = Item::create($data + ['is_active' => true]);

        if ($request->filled('opening_quantity') && (float) $request->opening_quantity > 0) {
            $this->stock->receive($item, Location::findOrFail($request->input('opening_location_id', Location::value('id'))),
                (float) $request->opening_quantity, $request->input('opening_cost', 0), $request->user(), ['reference' => 'Opening stock']);
        }

        return redirect()->route('inventory.items.show', $item)->with('success', $item->name . ' added.');
    }

    public function show(Item $item)
    {
        $item->load('category', 'supplier');

        return view('modules.inventory.items.show', [
            'item' => $item,
            'stock' => $item->stock()->with('location')->get()->sortBy('location.name')->values(),
            'movements' => $item->movements()->with('location', 'creator', 'recipient', 'purchaseOrder', 'project')->latest('id')->paginate(25),
            'locations' => Location::where('is_active', true)->orderBy('name')->get(),
            'staff' => User::staff()->orderBy('name')->get(['id', 'name']),
            'projects' => class_exists(Project::class) ? Project::whereIn('status', ['planning', 'active', 'on_hold'])->orderBy('name')->get(['id', 'code', 'name']) : collect(),
        ]);
    }

    public function edit(Item $item)
    {
        return view('modules.inventory.items.form', $this->formData($item));
    }

    public function update(Request $request, Item $item)
    {
        $data = $request->validate($this->rules($item));
        $item->update($data + ['is_active' => $request->boolean('is_active')]);

        return redirect()->route('inventory.items.show', $item)->with('success', 'Saved.');
    }

    public function receive(Request $request, Item $item)
    {
        $d = $request->validate(['location_id' => ['required', 'exists:inv_locations,id'], 'quantity' => ['required', 'numeric', 'gt:0'],
            'unit_cost' => ['nullable', 'numeric', 'min:0'], 'reference' => ['nullable', 'string', 'max:100'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->stock->receive($item, Location::find($d['location_id']), (float) $d['quantity'], $d['unit_cost'] ?? $item->avg_cost, $request->user(), $d);

        return back()->with('success', 'Stock received.');
    }

    public function issue(Request $request, Item $item)
    {
        $d = $request->validate(['location_id' => ['required', 'exists:inv_locations,id'], 'quantity' => ['required', 'numeric', 'gt:0'],
            'issued_to' => ['nullable', 'exists:users,id'], 'project_id' => ['nullable', 'exists:projects,id'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->stock->issue($item, Location::find($d['location_id']), (float) $d['quantity'], $request->user(), $d);

        return back()->with('success', 'Stock issued.');
    }

    public function adjust(Request $request, Item $item)
    {
        $d = $request->validate(['location_id' => ['required', 'exists:inv_locations,id'], 'quantity' => ['required', 'numeric', 'min:0'], 'note' => ['required', 'string', 'max:255']]);
        $m = $this->stock->adjust($item, Location::find($d['location_id']), (float) $d['quantity'], $request->user(), $d['note']);

        return back()->with('success', $m ? 'Count updated (' . ($m->quantity > 0 ? '+' : '') . Item::qty($m->quantity) . ').' : 'The count already matched.');
    }

    public function transfer(Request $request, Item $item)
    {
        $d = $request->validate(['location_id' => ['required', 'exists:inv_locations,id'], 'to_location_id' => ['required', 'exists:inv_locations,id'],
            'quantity' => ['required', 'numeric', 'gt:0'], 'note' => ['nullable', 'string', 'max:255']]);
        $this->stock->transfer($item, Location::find($d['location_id']), Location::find($d['to_location_id']), (float) $d['quantity'], $request->user(), $d['note'] ?? null);

        return back()->with('success', 'Stock moved.');
    }

    protected function formData(Item $item): array
    {
        return ['item' => $item, 'categories' => Category::kind('stock')->get(), 'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(),
            'locations' => Location::where('is_active', true)->orderBy('name')->get()];
    }

    protected function rules(?Item $item = null): array
    {
        return [
            'sku' => $item ? ['required', 'string', 'max:40', Rule::unique('inv_items', 'sku')->ignore($item)] : ['nullable', 'string', 'max:40'],
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:inv_categories,id'],
            'unit' => ['required', 'string', 'max:20'],
            'reorder_level' => ['nullable', 'numeric', 'min:0'],
            'supplier_id' => ['nullable', 'exists:inv_suppliers,id'],
            'description' => ['nullable', 'string', 'max:500'],
        ];
    }

    protected function suggestSku(string $name): string
    {
        $base = strtoupper(Str::of($name)->ascii()->replaceMatches('/[^A-Za-z0-9]+/', ' ')->trim()->explode(' ')->map(fn ($w) => Str::substr($w, 0, 3))->take(3)->implode('-')) ?: 'ITEM';
        $sku = $base;
        for ($n = 2; Item::where('sku', $sku)->exists(); $n++) $sku = $base . '-' . $n;

        return $sku;
    }
}
