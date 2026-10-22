<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Crm\Activity;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Services\Crm\CrmService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Notes, calls, meetings and follow-ups on a company, contact or deal. */
class ActivityController extends Controller
{
    public function __construct(protected CrmService $crm) {}

    public function store(Request $request, string $type, int $id)
    {
        $subject = match ($type) {
            'company' => Company::findOrFail($id),
            'contact' => Contact::findOrFail($id),
            'deal'    => Deal::findOrFail($id),
            default   => abort(404),
        };
        $data = $request->validate([
            'type'        => ['required', Rule::in(Activity::LOGGABLE)],
            'body'        => ['required', 'string', 'max:5000'],
            'due_at'      => ['nullable', 'date'],
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $this->crm->log($subject, $data, $request->user());

        return back()->with('success', $data['type'] === 'follow_up' ? 'Follow-up scheduled.' : 'Added to the timeline.');
    }

    public function complete(Request $request, Activity $activity)
    {
        abort_unless($activity->type === 'follow_up', 404);
        abort_unless($activity->assigned_to === $request->user()->id || $activity->user_id === $request->user()->id || $request->user()->can('Manage clients'), 403);
        $this->crm->complete($activity);

        return back();
    }

    public function destroy(Request $request, Activity $activity)
    {
        abort_unless($activity->type !== 'stage' && ($activity->user_id === $request->user()->id || $request->user()->can('Manage clients')), 403);
        $activity->delete();

        return back()->with('success', 'Removed.');
    }

    /** Everyone's open follow-ups, or only mine. */
    public function followUps(Request $request)
    {
        $mine = $request->query('who', 'me') === 'me';
        $items = Activity::with('subject', 'company', 'assignee')->pending()
            ->when($mine, fn ($q) => $q->where('assigned_to', $request->user()->id))
            ->orderBy('due_at')->paginate(40)->withQueryString();

        return view('modules.crm.follow-ups', ['items' => $items, 'mine' => $mine, 'crm' => $this->crm]);
    }
}
