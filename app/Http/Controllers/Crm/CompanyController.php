<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Finance\Invoice;
use App\Models\User;
use App\Services\Crm\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CompanyController extends Controller
{
    public function __construct(protected CrmService $crm) {}

    public function index(Request $request)
    {
        $status = $request->query('status');
        $companies = Company::with('owner')->withCount(['contacts', 'deals as open_deals_count' => fn ($q) => $q->open()])
            ->when(isset(Company::STATUSES[$status]), fn ($q) => $q->where('status', $status))
            ->when($request->query('owner') === 'me', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('industry', 'like', "%{$s}%")
                ->orWhereHas('contacts', fn ($c) => $c->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%"))))
            ->orderBy('name')->paginate(25)->withQueryString();

        return view('modules.crm.companies.index', [
            'companies' => $companies,
            'counts'    => Company::selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
        ]);
    }

    public function create()
    {
        return view('modules.crm.companies.form', ['company' => new Company(['status' => 'prospect']), 'staff' => $this->staff()]);
    }

    public function store(Request $request)
    {
        $company = $this->crm->saveCompany($this->validated($request), $request->user());

        return redirect()->route('crm.companies.show', $company)->with('success', 'Company added. Add a contact next.');
    }

    public function show(Company $company)
    {
        $company->load('owner', 'contacts.user');
        $userIds = $company->contacts->pluck('user_id')->filter();

        return view('modules.crm.companies.show', [
            'company'    => $company,
            'deals'      => $company->deals()->with('owner')->orderByRaw("case when stage in ('won','lost') then 1 else 0 end")->latest()->get(),
            'activities' => $company->activities()->with('user', 'assignee', 'subject')->limit(50)->get(),
            'projects'   => $company->projects()->latest()->get(),
            'invoices'   => $userIds->isEmpty() ? collect() : Invoice::whereIn('user_id', $userIds)->latest('issue_date')->limit(10)->get(),
            'staff'      => $this->staff(),
        ]);
    }

    public function edit(Company $company)
    {
        return view('modules.crm.companies.form', ['company' => $company, 'staff' => $this->staff()]);
    }

    public function update(Request $request, Company $company)
    {
        $this->crm->saveCompany($this->validated($request), $request->user(), $company);

        return redirect()->route('crm.companies.show', $company)->with('success', 'Saved.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'name'     => ['required', 'string', 'max:150'],
            'status'   => ['required', Rule::in(array_keys(Company::STATUSES))],
            'industry' => ['nullable', 'string', 'max:100'],
            'website'  => ['nullable', 'string', 'max:200'],
            'email'    => ['nullable', 'email', 'max:150'],
            'phone'    => ['nullable', 'string', 'max:40'],
            'address'  => ['nullable', 'string', 'max:255'],
            'city'     => ['nullable', 'string', 'max:100'],
            'country'  => ['nullable', 'string', 'max:100'],
            'source'   => ['nullable', 'string', 'max:50'],
            'notes'    => ['nullable', 'string', 'max:5000'],
            'owner_id' => ['nullable', Rule::exists('users', 'id')->where('user_type', User::TYPE_STAFF)],
        ]);
    }

    protected function staff()
    {
        return User::where('user_type', User::TYPE_STAFF)->orderBy('name')->get(['id', 'name']);
    }
}
