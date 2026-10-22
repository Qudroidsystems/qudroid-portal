<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\User;
use App\Services\Crm\CrmService;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function __construct(protected CrmService $crm) {}

    public function index(Request $request)
    {
        $contacts = Contact::with('company', 'user')
            ->when(trim((string) $request->query('q')), fn ($q, $s) => $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%")))
            ->when($request->boolean('portal'), fn ($q) => $q->whereNotNull('user_id'))
            ->orderBy('name')->paginate(30)->withQueryString();

        return view('modules.crm.contacts.index', ['contacts' => $contacts, 'companies' => Company::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request)
    {
        $contact = $this->crm->saveContact($this->validated($request));

        return $request->boolean('back')
            ? back()->with('success', 'Contact added.')
            : redirect()->route('crm.contacts.show', $contact)->with('success', 'Contact added.');
    }

    public function show(Contact $contact)
    {
        $contact->load('company', 'user', 'deals');

        return view('modules.crm.contacts.show', [
            'contact'    => $contact,
            'activities' => $contact->activities()->with('user', 'assignee')->get(),
            'companies'  => Company::orderBy('name')->get(['id', 'name']),
            'staff'      => User::where('user_type', User::TYPE_STAFF)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $this->crm->saveContact($this->validated($request), $contact);

        return back()->with('success', 'Contact saved.');
    }

    public function destroy(Contact $contact)
    {
        abort_if($contact->user_id !== null, 422, 'This contact has portal access. Remove the account first.');
        $company = $contact->company;
        $contact->delete();

        return $company ? redirect()->route('crm.companies.show', $company)->with('success', 'Contact removed.') : redirect()->route('crm.contacts.index');
    }

    /** Create (or link) the contact's client portal account. */
    public function access(Contact $contact)
    {
        [$user, $password] = $this->crm->giveAccess($contact);
        PortalNotifier::toUsers([$user->id], 'Welcome to ' . config('qudroid.name'), 'You can follow your projects and invoices here.', route('projects.index'), 'system');

        return back()->with('success', $password ? 'Portal account created for ' . $user->email . '.' : 'Linked to the existing client account ' . $user->email . '.')
            ->with('client_password', $password);
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'company_id' => ['nullable', 'exists:crm_companies,id'],
            'name'       => ['required', 'string', 'max:150'],
            'job_title'  => ['nullable', 'string', 'max:100'],
            'email'      => ['nullable', 'email', 'max:150'],
            'phone'      => ['nullable', 'string', 'max:40'],
            'is_primary' => ['nullable', 'boolean'],
            'notes'      => ['nullable', 'string', 'max:5000'],
        ]);
        $data['is_primary'] = (bool) ($data['is_primary'] ?? false);

        return $data;
    }
}
