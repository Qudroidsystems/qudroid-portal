<?php

namespace App\Http\Controllers\Workflows;

use App\Http\Controllers\Controller;
use App\Models\Connect\Conversation;
use App\Models\Connect\Participant;
use App\Models\Projects\Project;
use App\Models\User;
use App\Models\Workflows\Workflow;
use App\Models\Workflows\WorkflowRun;
use App\Services\Workflows\Definition;
use App\Services\Workflows\Engine;
use App\Services\Workflows\Recipes;
use App\Services\Workflows\Steps;
use App\Services\Workflows\Triggers;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Role;

class WorkflowController extends Controller
{
    public function index()
    {
        $workflows = Workflow::with('creator:id,name')->withCount(['runs as failed_count' => fn ($q) => $q->where('status', 'failed')->where('created_at', '>=', now()->subDays(7))])
            ->orderByDesc('is_active')->orderBy('name')->get();
        $recent = WorkflowRun::with('workflow:id,name')->latest('id')->limit(15)->get();

        return view('workflows.index', ['workflows' => $workflows, 'recent' => $recent, 'recipes' => Recipes::all()]);
    }

    public function create(Request $request)
    {
        $recipe = Recipes::all()[$request->query('recipe')] ?? null;
        $wf = new Workflow([
            'name' => $recipe['name'] ?? '', 'description' => $recipe['description'] ?? '',
            'definition' => $recipe['definition'] ?? ['trigger' => ['key' => '', 'config' => []], 'conditions' => ['match' => 'all', 'rules' => []], 'steps' => []],
        ]);

        return $this->builder($wf, $request);
    }

    public function edit(Request $request, Workflow $workflow)
    {
        return $this->builder($workflow, $request);
    }

    protected function builder(Workflow $wf, Request $request)
    {
        $me = $request->user();
        // Chats a workflow can post in: every channel, and groups the builder is in (it posts as them).
        $mine = Participant::where('user_id', $me->id)->whereNull('left_at')->pluck('conversation_id');
        $chats = Conversation::where(fn ($q) => $q->where('type', 'channel')->orWhere(fn ($g) => $g->where('type', 'group')->whereIn('id', $mine)))
            ->whereNull('archived_at')->orderBy('type')->orderBy('name')->get(['id', 'type', 'name'])
            ->map(fn ($c) => ['id' => $c->id, 'label' => ($c->type === 'channel' ? '#' : '') . $c->name . ($c->type === 'group' ? ' (group)' : '')]);
        // Keep a chat chosen by someone else visible when editing their workflow.
        foreach ($wf->steps() as $s) {
            $id = (int) ($s['config']['conversation_id'] ?? 0);
            if ($s['type'] === 'chat' && $id && !$chats->firstWhere('id', $id) && ($c = Conversation::find($id))) $chats->push(['id' => $c->id, 'label' => $c->name]);
        }

        return view('workflows.builder', [
            'wf' => $wf,
            'catalog' => [
                'triggers' => Triggers::forBuilder(),
                'groups' => collect(Triggers::GROUPS)->map(fn ($g) => ['label' => $g[0], 'icon' => $g[1]]),
                'steps' => collect(Steps::TYPES)->map(fn ($s) => ['label' => $s[0], 'icon' => $s[1], 'hint' => $s[2]]),
                'ops' => Steps::OPS, 'units' => Steps::UNITS,
                'priorities' => Project::PRIORITIES,
                'users' => User::where('is_disabled', false)->where('user_type', User::TYPE_STAFF)->orderBy('name')->get(['id', 'name'])->map(fn ($u) => ['id' => $u->id, 'label' => $u->name])->values(),
                'roles' => Role::orderBy('name')->pluck('name')->values(),
                'chats' => $chats->values(),
                'projects' => Project::whereIn('status', Project::OPEN)->orderBy('name')->get(['id', 'code', 'name'])->map(fn ($p) => ['id' => $p->id, 'label' => $p->code . ' · ' . $p->name])->values(),
            ],
        ]);
    }

    public function store(Request $request)
    {
        [$meta, $clean] = $this->validated($request);
        $wf = Workflow::create($meta + $clean + ['created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'is_active' => $request->boolean('is_active')]);

        return $this->saved($request, $wf, 'Workflow saved' . ($wf->is_active ? ' and switched on.' : '. Switch it on when you are ready.'));
    }

    public function update(Request $request, Workflow $workflow)
    {
        [$meta, $clean] = $this->validated($request);
        $workflow->update($meta + $clean + ['updated_by' => $request->user()->id, 'is_active' => $request->boolean('is_active')]);

        return $this->saved($request, $workflow, 'Workflow saved.');
    }

    protected function validated(Request $request): array
    {
        $meta = $request->validate(['name' => ['required', 'string', 'max:150'], 'description' => ['nullable', 'string', 'max:500'], 'definition' => ['required', 'string', 'max:200000']]);
        $clean = Definition::clean($meta['definition']);

        return [['name' => $meta['name'], 'description' => $meta['description'] ?? null], $clean];
    }

    protected function saved(Request $request, Workflow $wf, string $msg)
    {
        if ($request->expectsJson()) return response()->json(['ok' => true, 'id' => $wf->id, 'message' => $msg, 'edit' => route('workflows.edit', $wf)]);

        return redirect()->route('workflows.edit', $wf)->with('success', $msg);
    }

    public function toggle(Request $request, Workflow $workflow)
    {
        if (!$workflow->is_active) Definition::clean($workflow->definition); // still valid (a chat or project may have gone)
        $workflow->update(['is_active' => !$workflow->is_active, 'updated_by' => $request->user()->id]);
        $msg = $workflow->is_active ? '"' . $workflow->name . '" is on.' : '"' . $workflow->name . '" is off.';

        return $request->expectsJson() ? response()->json(['ok' => true, 'active' => $workflow->is_active, 'message' => $msg]) : back()->with('success', $msg);
    }

    public function duplicate(Request $request, Workflow $workflow)
    {
        $copy = $workflow->replicate(['last_run_at', 'runs_count']);
        $copy->fill(['name' => mb_substr('Copy of ' . $workflow->name, 0, 150), 'is_active' => false, 'created_by' => $request->user()->id, 'updated_by' => $request->user()->id, 'runs_count' => 0])->save();

        return redirect()->route('workflows.edit', $copy)->with('success', 'Copied. The copy is off until you switch it on.');
    }

    public function destroy(Workflow $workflow)
    {
        $name = $workflow->name;
        $workflow->delete();

        return redirect()->route('workflows.index')->with('success', '"' . $name . '" deleted.');
    }

    /** A dry run against the newest matching record: says what each step would do, does nothing. */
    public function preview(Request $request)
    {
        $data = $request->validate(['definition' => ['required', 'string', 'max:200000']]);
        $clean = Definition::clean($data['definition']);
        $wf = new Workflow($clean + ['name' => (string) $request->input('name', 'Workflow')]);
        $wf->setRelation('creator', $request->user());

        return response()->json(['ok' => true] + Engine::preview($wf));
    }

    public function runs(Request $request, Workflow $workflow)
    {
        $runs = $workflow->runs()->latest('id')
            ->when(in_array($request->query('status'), array_keys(WorkflowRun::STATUSES), true), fn ($q) => $q->where('status', $request->query('status')))
            ->paginate(25)->withQueryString();

        return view('workflows.runs', ['wf' => $workflow, 'runs' => $runs, 'trigger' => Triggers::get($workflow->trigger)]);
    }

    public function retry(Request $request, WorkflowRun $run)
    {
        abort_unless($run->status === 'failed', 422, 'Only a failed run can be tried again.');
        $run->update(['status' => 'queued', 'error' => null, 'finished_at' => null]);
        Engine::process($run);
        $run->refresh();

        return back()->with($run->status === 'failed' ? 'error' : 'success', $run->status === 'failed' ? 'It failed again: ' . $run->error : 'Run finished: ' . $run->statusLabel() . '.');
    }
}
