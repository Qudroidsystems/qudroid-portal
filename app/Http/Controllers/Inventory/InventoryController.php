<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Category;
use App\Models\Inventory\Item;
use App\Models\Inventory\Location;
use App\Models\Inventory\Movement;
use App\Models\Inventory\PurchaseOrder;
use App\Services\Inventory\StockService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class InventoryController extends Controller
{
    public function index(StockService $stock)
    {
        return view('modules.inventory.index', [
            'stockValue' => $stock->valueMinor(),
            'itemCount' => Item::where('is_active', true)->count(),
            'low' => $stock->lowStock(),
            'assetCounts' => Asset::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'openOrders' => PurchaseOrder::with('supplier')->whereIn('status', ['ordered', 'partial'])->orderBy('expected_on')->limit(8)->get(),
            'recent' => Movement::with('item', 'location', 'creator', 'recipient')->latest('id')->limit(10)->get(),
            'warranty' => Asset::whereNotIn('status', ['retired', 'lost'])->whereDate('warranty_until', '>=', today())
                ->whereDate('warranty_until', '<=', today()->addDays(60))->orderBy('warranty_until')->limit(6)->get(),
        ]);
    }

    public function movements(Request $request)
    {
        $q = Movement::with('item', 'location', 'creator', 'recipient', 'purchaseOrder', 'project')
            ->when($request->filled('type'), fn ($w) => $w->where('type', $request->type))
            ->when($request->filled('location'), fn ($w) => $w->where('location_id', $request->location))
            ->when($request->filled('from'), fn ($w) => $w->whereDate('created_at', '>=', $request->from))
            ->when($request->filled('to'), fn ($w) => $w->whereDate('created_at', '<=', $request->to));

        return view('modules.inventory.movements', [
            'movements' => $q->latest('id')->paginate(40)->withQueryString(),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function settings()
    {
        return view('modules.inventory.settings', [
            'categories' => Category::orderBy('kind')->orderBy('name')->get()->groupBy('kind'),
            'locations' => Location::orderBy('name')->get(),
        ]);
    }

    public function storeCategory(Request $request)
    {
        $data = $request->validate(['kind' => ['required', 'in:stock,asset'], 'name' => ['required', 'string', 'max:80',
            Rule::unique('inv_categories')->where('kind', $request->kind)]]);
        Category::create($data);

        return back()->with('success', 'Category added.');
    }

    public function updateCategory(Request $request, Category $category)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('inv_categories')->where('kind', $category->kind)->ignore($category)]]);
        $category->update($data);

        return back()->with('success', 'Category saved.');
    }

    public function storeLocation(Request $request)
    {
        Location::create($request->validate(['name' => ['required', 'string', 'max:80', 'unique:inv_locations,name'], 'description' => ['nullable', 'string', 'max:255']]));

        return back()->with('success', 'Location added.');
    }

    public function updateLocation(Request $request, Location $location)
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:80', Rule::unique('inv_locations', 'name')->ignore($location)], 'description' => ['nullable', 'string', 'max:255']]);
        $location->update($data + ['is_active' => $request->boolean('is_active')]);

        return back()->with('success', 'Location saved.');
    }
}
