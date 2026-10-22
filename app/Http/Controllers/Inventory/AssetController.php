<?php

namespace App\Http\Controllers\Inventory;

use App\Http\Controllers\Controller;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Category;
use App\Models\Inventory\Location;
use App\Models\Inventory\Supplier;
use App\Models\User;
use App\Services\Inventory\AssetService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AssetController extends Controller
{
    public function __construct(protected AssetService $assets) {}

    public function index(Request $request)
    {
        $status = $request->input('status', 'current');
        $q = Asset::with('category', 'holder', 'location')
            ->when($status === 'current', fn ($w) => $w->whereNotIn('status', ['retired', 'lost']))
            ->when(!in_array($status, ['current', 'all'], true), fn ($w) => $w->where('status', $status))
            ->when($request->filled('category'), fn ($w) => $w->where('category_id', $request->category))
            ->when($request->filled('holder'), fn ($w) => $w->where('assigned_to', $request->holder))
            ->when($request->filled('q'), fn ($w) => $w->where(fn ($x) => $x->where('name', 'like', '%' . $request->q . '%')->orWhere('tag', 'like', '%' . $request->q . '%')
                ->orWhere('serial_number', 'like', '%' . $request->q . '%')->orWhereHas('holder', fn ($u) => $u->where('name', 'like', '%' . $request->q . '%'))));

        return view('modules.inventory.assets.index', [
            'assets' => $q->orderBy('tag')->paginate(40)->withQueryString(),
            'categories' => Category::kind('asset')->get(),
            'counts' => Asset::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status'),
            'status' => $status,
        ]);
    }

    public function create()
    {
        return view('modules.inventory.assets.form', $this->formData(new Asset(['condition' => 'new', 'purchase_date' => today(), 'location_id' => Location::value('id')])));
    }

    public function store(Request $request)
    {
        $asset = $this->assets->create($request->validate($this->rules()), $request->user());

        return redirect()->route('inventory.assets.show', $asset)->with('success', $asset->name . ' added as ' . $asset->tag . '.');
    }

    public function show(Asset $asset)
    {
        $asset->load('category', 'supplier', 'holder', 'location', 'events.user', 'events.creator');

        return view('modules.inventory.assets.show', [
            'asset' => $asset,
            'staff' => User::staff()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function edit(Asset $asset)
    {
        return view('modules.inventory.assets.form', $this->formData($asset));
    }

    public function update(Request $request, Asset $asset)
    {
        $this->assets->update($asset, $request->validate($this->rules()));

        return redirect()->route('inventory.assets.show', $asset)->with('success', 'Saved.');
    }

    public function action(Request $request, Asset $asset)
    {
        $d = $request->validate([
            'do' => ['required', Rule::in(['assign', 'return', 'repair', 'repaired', 'retire', 'lost', 'note'])],
            'user_id' => ['nullable', 'required_if:do,assign', 'exists:users,id'],
            'condition' => ['nullable', Rule::in(array_keys(Asset::CONDITIONS))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $by = $request->user();
        $note = $d['note'] ?? null;

        match ($d['do']) {
            'assign' => $this->assets->assign($asset, User::findOrFail($d['user_id']), $by, $note),
            'return' => $this->assets->return($asset, $by, $d['condition'] ?? null, $note),
            'repair' => $this->assets->repair($asset, $by, $note),
            'repaired' => $this->assets->repaired($asset, $by, $note),
            'retire' => $this->assets->retire($asset, $by, (string) $note),
            'lost' => $this->assets->retire($asset, $by, (string) $note, true),
            'note' => $this->assets->note($asset, $by, (string) $note),
        };

        return back()->with('success', ['assign' => 'Asset handed over.', 'return' => 'Asset returned to store.', 'repair' => 'Sent for repair.',
            'repaired' => 'Back in store.', 'retire' => 'Asset retired.', 'lost' => 'Recorded as lost.', 'note' => 'Note added.'][$d['do']]);
    }

    public function mine(Request $request)
    {
        return view('modules.inventory.assets.mine', [
            'assets' => Asset::with('category', 'events')->where('assigned_to', $request->user()->id)->where('status', 'assigned')->orderBy('tag')->get(),
        ]);
    }

    protected function formData(Asset $asset): array
    {
        return ['asset' => $asset, 'categories' => Category::kind('asset')->get(), 'suppliers' => Supplier::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'locations' => Location::where('is_active', true)->orderBy('name')->get()];
    }

    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category_id' => ['nullable', 'exists:inv_categories,id'],
            'make_model' => ['nullable', 'string', 'max:150'],
            'serial_number' => ['nullable', 'string', 'max:100'],
            'supplier_id' => ['nullable', 'exists:inv_suppliers,id'],
            'purchase_date' => ['nullable', 'date'],
            'purchase_cost' => ['nullable', 'numeric', 'min:0'],
            'warranty_until' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(array_keys(Asset::CONDITIONS))],
            'location_id' => ['nullable', 'exists:inv_locations,id'],
            'notes' => ['nullable', 'string', 'max:5000'],
        ];
    }
}
