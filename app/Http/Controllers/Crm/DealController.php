<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Services\Crm\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class DealController extends Controller
{
    public function __construct(protected CrmService $crm) {}

    /** The pipeline board (open stages), or a list of every deal. */
    public function index(Request $request)
    {
        $list = $request->query('view') === 'list';
        $deals = Deal::with('company', 'owner')
            ->when(!$list, fn ($q) => $q->open())
            ->when($list && isset(Deal::STAGES[$request->query('stage')]), fn ($q) => $q->where('stage', $request->query('stage')))
            ->when($request->query('owner') === 'me', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$s}%"))))
            ->when($list, fn ($q) => $q->latest('updated_at'), fn ($q) => $q->orderBy('position')->orderBy('id'))
            ->get();

        return view('modules.crm.deals.index', [
            'deals'     => $deals,
            'list'      => $list,
            'columns'   => collect(Deal::OPEN)->mapWithKeys(fn ($s) => [$s => $deals->where('stage', $s)->values()]),
            'companies' => Company::orderBy('name')->get(['id', 'name']),
            'stats'     => $this->crm->stats(),
        ]);
    }

    public function store(Request $request)
    {
        $deal = $this->crm->saveDeal($this->validated($request), $request->user());

        return $request->boolean('back')
            ? back()->with('success', 'Deal added.')
            : redirect()->route('crm.deals.show', $deal)->with('success', 'Deal added to the pipeline.');
    }

    public function show(Deal $deal)
    {
        $deal->load('company.contacts', 'contact', 'owner', 'project');

        return view('modules.crm.deals.show', [
            'deal'       => $deal,
            'activities' => $deal->activities()->with('user', 'assignee')->get(),
            'companies'  => Company::orderBy('name')->get(['id', 'name']),
            'contacts'   => $deal->company ? $deal->company->contacts : Contact::whereNull('company_id')->orderBy('name')->get(),
            'staff'      => User::where('user_type', User::TYPE_STAFF)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Deal $deal)
    {
        $data = $this->validated($request, true);
        $this->crm->saveDeal($data, $request->user(), $deal);

        return back()->with('success', 'Deal saved.');
    }

    public function move(Request $request, Deal $deal)
    {
        $data = $request->validate(['stage' => ['required', Rule::in(Deal::OPEN)], 'order' => ['array'], 'order.*' => ['integer']]);
        $this->crm->move($deal, $data['stage'], $data['order'] ?? [], $request->user());

        return response()->json(['ok' => true]);
    }

    public function win(Request $request, Deal $deal)
    {
        $this->crm->win($deal, $request->user());

        return back()->with('success', 'Marked won. The company is now a client.');
    }

    public function lose(Request $request, Deal $deal)
    {
        $this->crm->lose($deal, $request->user(), (string) $request->input('reason'));

        return back()->with('success', 'Marked lost.');
    }

    public function reopen(Request $request, Deal $deal)
    {
        $this->crm->reopen($deal, $request->user());

        return back()->with('success', 'Reopened.');
    }

    public function destroy(Deal $deal)
    {
        $deal->activities()->delete();
        $deal->delete();

        return redirect()->route('crm.deals.index')->with('success', 'Deal deleted.');
    }

    protected function validated(Request $request, bool $updating = false): array
    {
        $data = $request->validate([
            'title'             => ['required', 'string', 'max:200'],
            'company_id'        => ['nullable', 'exists:crm_companies,id'],
            'contact_id'        => ['nullable', 'exists:crm_contacts,id'],
            'value'             => ['nullable', 'numeric', 'min:0', 'max:9999999999'],
            'stage'             => [$updating ? 'prohibited' : 'nullable', Rule::in(Deal::OPEN)],
            'probability'       => ['nullable', 'integer', 'min:0', 'max:100'],
            'expected_close_on' => ['nullable', 'date'],
            'source'            => ['nullable', 'string', 'max:50'],
            'notes'             => ['nullable', 'string', 'max:5000'],
            'owner_id'          => ['nullable', Rule::exists('users', 'id')->where('user_type', User::TYPE_STAFF)],
        ]);
        $data['value'] ??= 0;
        if ($updating && !isset($data['probability'])) unset($data['probability']);

        return $data;
    }
}
