<?php

namespace App\Http\Controllers\Api\V1\Crm;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\User;
use App\Services\Crm\CrmService;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** CRM on the phone: look people up, move deals, log calls and follow-ups, quick-add. View clients reads; Manage clients changes. */
class CrmApiController extends ApiController
{
    public function __construct(protected CrmService $crm) {}

    public function companies(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $q = Company::with('owner');
        if ($s = trim((string) $request->get('q'))) $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('industry', 'like', "%{$s}%")->orWhere('city', 'like', "%{$s}%"));
        if (isset(Company::STATUSES[$request->get('status')])) $q->where('status', $request->get('status'));
        $page = $q->orderBy('name')->paginate(min(50, max(5, (int) $request->get('per_page', 30))));
        $open = Deal::open()->whereIn('company_id', collect($page->items())->pluck('id'))->selectRaw('company_id, sum(value) as v, count(*) as n')->groupBy('company_id')->get()->keyBy('company_id');

        return $this->paged($page, fn ($c) => $this->companyItem($c) + [
            'open_deals' => (int) ($open[$c->id]->n ?? 0), 'open_deals_value' => Money::minor($open[$c->id]->v ?? 0),
            'open_deals_label' => Money::format($open[$c->id]->v ?? 0)]);
    }

    public function company(Request $request, Company $company)
    {
        if ($deny = $this->deny($request)) return $deny;
        $company->load('owner', 'contacts', 'deals.owner', 'deals.contact');

        return $this->ok($this->companyItem($company) + [
            'website' => $company->website, 'email' => $company->email, 'phone' => $company->phone, 'address' => $company->address, 'country' => $company->country,
            'source' => $company->source, 'notes' => $company->notes,
            'contacts' => $company->contacts->map(fn ($c) => $this->contactItem($c))->values(),
            'deals' => $company->deals->sortByDesc('id')->map(fn ($d) => $this->dealItem($d->setRelation('company', $company)))->values(),
            'activities' => $this->timeline(Activity::with('user', 'assignee', 'subject')->where('company_id', $company->id)),
        ]);
    }

    public function contacts(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $q = Contact::with('company');
        if ($s = trim((string) $request->get('q'))) $q->where(fn ($w) => $w->where('name', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")->orWhere('phone', 'like', "%{$s}%"));
        if ($request->filled('company_id')) $q->where('company_id', (int) $request->get('company_id'));

        return $this->paged($q->orderBy('name')->paginate(min(50, max(5, (int) $request->get('per_page', 30)))), fn ($c) => $this->contactItem($c));
    }

    public function contact(Request $request, Contact $contact)
    {
        if ($deny = $this->deny($request)) return $deny;
        $contact->load('company', 'deals.owner', 'deals.company');

        return $this->ok($this->contactItem($contact) + [
            'notes' => $contact->notes, 'has_portal_access' => (bool) $contact->user_id,
            'deals' => $contact->deals->map(fn ($d) => $this->dealItem($d))->values(),
            'activities' => $this->timeline(Activity::with('user', 'assignee', 'subject')->where('subject_type', $contact->getMorphClass())->where('subject_id', $contact->id)),
        ]);
    }

    /** ?stage=&mine=1&q= — the board, with column totals in meta.stages */
    public function deals(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $q = Deal::with('company', 'contact', 'owner');
        $stage = $request->get('stage', 'open');
        if ($stage === 'open') $q->open();
        elseif (isset(Deal::STAGES[$stage])) $q->where('stage', $stage);
        if ($request->boolean('mine')) $q->where('owner_id', $request->user()->id);
        if ($s = trim((string) $request->get('q'))) $q->where(fn ($w) => $w->where('title', 'like', "%{$s}%")->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%{$s}%")));
        $deals = $q->orderBy('position')->orderByDesc('id')->limit(500)->get();

        $stages = collect(Deal::STAGES)->map(fn ($s, $key) => ['key' => $key, 'label' => $s[0], 'open' => in_array($key, Deal::OPEN, true),
            'count' => $deals->where('stage', $key)->count(), 'total' => Money::minor($deals->where('stage', $key)->sum('value')),
            'total_label' => Money::format($deals->where('stage', $key)->sum('value'))])->values();

        return $this->ok($deals->map(fn ($d) => $this->dealItem($d))->values(), null, 200, ['stages' => $stages]);
    }

    public function deal(Request $request, Deal $deal)
    {
        if ($deny = $this->deny($request)) return $deny;
        $deal->load('company', 'contact', 'owner');

        return $this->ok($this->dealItem($deal) + ['notes' => $deal->notes, 'source' => $deal->source, 'lost_reason' => $deal->lost_reason,
            'won_at' => $deal->won_at?->toIso8601String(), 'lost_at' => $deal->lost_at?->toIso8601String(),
            'activities' => $this->timeline(Activity::with('user', 'assignee', 'subject')->where('subject_type', $deal->getMorphClass())->where('subject_id', $deal->id))]);
    }

    /** {stage: open stage|won|lost, position?, lost_reason?} */
    public function moveDeal(Request $request, Deal $deal)
    {
        if ($deny = $this->deny($request, true)) return $deny;
        $d = $request->validate(['stage' => ['required', Rule::in(array_keys(Deal::STAGES))], 'position' => ['nullable', 'integer', 'min:1'], 'lost_reason' => ['nullable', 'string', 'max:255']]);
        $by = $request->user();
        try {
            if ($d['stage'] === 'won') $deal = $this->crm->win($deal, $by);
            elseif ($d['stage'] === 'lost') $deal = $this->crm->lose($deal, $by, (string) ($d['lost_reason'] ?? ''));
            else {
                if (!$deal->isOpen()) $deal = $this->crm->reopen($deal, $by);
                $order = Deal::where('stage', $d['stage'])->whereKeyNot($deal->id)->orderBy('position')->pluck('id')->all();
                array_splice($order, min(count($order), max(0, (int) ($d['position'] ?? count($order) + 1) - 1)), 0, [$deal->id]);
                $deal = $this->crm->move($deal, $d['stage'], $order, $by);
            }
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'validation', $e->errors());
        }

        return $this->ok($this->dealItem($deal->fresh(['company', 'contact', 'owner'])));
    }

    /** ?due=today|overdue|upcoming|all&mine=1 (default mine) — follow-ups */
    public function activities(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $q = Activity::with('subject', 'company', 'assignee', 'user')->pending();
        if ($request->boolean('mine', true)) $q->where('assigned_to', $request->user()->id);
        match ($request->get('due', 'all')) {
            'today' => $q->whereDate('due_at', '<=', today()),
            'overdue' => $q->where('due_at', '<', now()),
            'upcoming' => $q->where('due_at', '>=', now()),
            default => null,
        };

        return $this->ok($q->orderBy('due_at')->limit(200)->get()->map(fn ($a) => $this->activity($a))->values());
    }

    /** {subject_type: company|contact|deal, subject_id, type: note|call|email|meeting|follow_up, body, due_at?, assigned_to?} */
    public function storeActivity(Request $request)
    {
        if ($deny = $this->deny($request)) return $deny;
        $d = $request->validate([
            'subject_type' => ['required', Rule::in(['company', 'contact', 'deal'])], 'subject_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(Activity::LOGGABLE)], 'body' => ['required', 'string', 'max:5000'],
            'due_at' => ['nullable', 'date'], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $subject = match ($d['subject_type']) { 'company' => Company::find($d['subject_id']), 'contact' => Contact::find($d['subject_id']), default => Deal::find($d['subject_id']) };
        if (!$subject) return $this->fail('Not found.', 404, 'not_found');
        $a = $this->crm->log($subject, $d, $request->user());

        return $this->ok($this->activity($a->load('subject', 'user', 'assignee')), $a->type === 'follow_up' ? 'Follow-up scheduled.' : 'Added to the timeline.', 201);
    }

    /** Marks a follow-up done (again to undo). */
    public function completeActivity(Request $request, Activity $activity)
    {
        if ($deny = $this->deny($request)) return $deny;

        return $this->ok($this->activity($this->crm->complete($activity)->load('subject', 'user', 'assignee')));
    }

    public function storeCompany(Request $request)
    {
        if ($deny = $this->deny($request, true)) return $deny;
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'status' => ['nullable', Rule::in(array_keys(Company::STATUSES))],
            'industry' => ['nullable', 'string', 'max:100'], 'website' => ['nullable', 'string', 'max:200'], 'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:40'], 'city' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $c = $this->crm->saveCompany($d + ['status' => $d['status'] ?? 'prospect'], $request->user());

        return $this->ok($this->companyItem($c->load('owner')), 'Company added.', 201);
    }

    public function storeContact(Request $request)
    {
        if ($deny = $this->deny($request, true)) return $deny;
        $d = $request->validate([
            'name' => ['required', 'string', 'max:150'], 'company_id' => ['nullable', 'integer', 'exists:crm_companies,id'],
            'job_title' => ['nullable', 'string', 'max:100'], 'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:40'],
            'is_primary' => ['nullable', 'boolean'], 'notes' => ['nullable', 'string', 'max:5000'],
        ]);
        $c = $this->crm->saveContact($d + ['is_primary' => (bool) ($d['is_primary'] ?? false)]);

        return $this->ok($this->contactItem($c->load('company')), 'Contact added.', 201);
    }

    // ── Shapes ──────────────────────────────────────────────────────────

    protected function companyItem(Company $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'status' => $c->status, 'status_label' => $c->status_label, 'industry' => $c->industry, 'city' => $c->city,
            'owner' => $c->owner ? ['id' => $c->owner->id, 'name' => $c->owner->name] : null, 'web_url' => route('crm.companies.show', $c)];
    }

    protected function contactItem(Contact $c): array
    {
        return ['id' => $c->id, 'name' => $c->name, 'job_title' => $c->job_title, 'email' => $c->email, 'phone' => $c->phone, 'is_primary' => $c->is_primary,
            'company' => $c->company ? ['id' => $c->company->id, 'name' => $c->company->name] : null, 'web_url' => route('crm.contacts.show', $c)];
    }

    protected function dealItem(Deal $d): array
    {
        return ['id' => $d->id, 'title' => $d->title, 'company' => $d->company ? ['id' => $d->company->id, 'name' => $d->company->name] : null,
            'contact' => $d->contact ? ['id' => $d->contact->id, 'name' => $d->contact->name] : null]
            + $this->money('value', $d->value, $d->currency) + [
            'stage' => $d->stage, 'stage_label' => $d->stage_label, 'open' => $d->isOpen(), 'probability' => $d->probability,
            'expected_close_on' => $d->expected_close_on?->toDateString(), 'late' => $d->isLate(),
            'owner' => $d->owner ? ['id' => $d->owner->id, 'name' => $d->owner->name] : null, 'position' => $d->position,
            'web_url' => route('crm.deals.show', $d)];
    }

    protected function activity(Activity $a): array
    {
        $s = $a->subject;
        $type = $s instanceof Deal ? 'deal' : ($s instanceof Contact ? 'contact' : 'company');

        return ['id' => $a->id, 'type' => $a->type, 'type_label' => $a->type_label, 'body' => $a->body,
            'due_at' => $a->due_at?->toIso8601String(), 'done_at' => $a->done_at?->toIso8601String(), 'overdue' => $a->isOverdue(),
            'subject' => $s ? ['type' => $type, 'id' => $s->id, 'name' => $s instanceof Deal ? $s->title : $s->name] : null,
            'by' => $a->user ? ['id' => $a->user->id, 'name' => $a->user->name] : null,
            'assigned_to' => $a->assignee ? ['id' => $a->assignee->id, 'name' => $a->assignee->name] : null,
            'at' => $a->created_at?->toIso8601String(), 'web_url' => $this->crm->link($a)];
    }

    protected function timeline($q): array
    {
        return $q->latest('id')->limit(50)->get()->map(fn ($a) => $this->activity($a))->values()->all();
    }

    protected function deny(Request $request, bool $manage = false)
    {
        $u = $request->user();
        $ok = $manage ? $u->can('Manage clients') : ($u->can('View clients') || $u->can('Manage clients'));

        return $ok ? null : $this->fail($manage ? 'You can\'t change CRM records.' : 'You do not have access to the CRM.', 403, 'forbidden');
    }
}
