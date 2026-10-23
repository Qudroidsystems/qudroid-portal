<?php

namespace App\Http\Controllers\Api\V1\Home;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\Academy\Application;
use App\Models\Academy\Enrollment;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Meetings\Meeting;
use App\Models\Projects\Task;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Hr\LeaveService;
use App\Services\Search\GlobalSearch;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** The app's home screen in one call, and search across modules. */
class HomeApiController extends ApiController
{
    public function home(Request $request)
    {
        $user = $request->user();
        $out = ['unread_notifications' => $user->unreadNotifications()->count()];

        $this->block($out, 'unread_messages', $user->can('Use connect'), fn () => app(ConnectService::class)->unreadTotal($user));
        $this->block($out, 'next_meetings', $user->can('View meetings') || $user->can('Manage meetings'), fn () => Meeting::for($user)
            ->where('status', 'scheduled')->where('ends_at', '>', now())->orderBy('starts_at')->limit(3)->get()
            ->map(fn ($m) => ['id' => $m->id, 'title' => $m->title, 'starts_at' => $m->starts_at->toIso8601String(), 'ends_at' => $m->ends_at->toIso8601String(),
                'live' => $m->starts_at->lte(now()), 'location' => $m->location, 'deep_link' => 'meetings/' . $m->id])->values());
        $this->block($out, 'my_tasks_due', $user->can('View projects') || $user->can('Manage projects'), fn () => Task::with('project:id,code,name')
            ->where('assignee_id', $user->id)->where('status', '!=', 'done')
            ->orderByRaw('case when due_on is null then 1 else 0 end')->orderBy('due_on')->limit(5)->get()
            ->map(fn ($t) => ['id' => $t->id, 'key' => $t->key_label, 'title' => $t->title, 'status' => $t->status, 'priority' => $t->priority,
                'due_on' => $t->due_on?->toDateString(), 'overdue' => $t->isOverdue(), 'project' => $t->project?->name,
                'deep_link' => 'projects/' . $t->project?->code . '/tasks/' . $t->number])->values());

        $student = $user->user_type === 'student' ? \App\Models\Academy\Student::where('user_id', $user->id)->first() : null;
        $this->block($out, 'my_courses', (bool) $student, fn () => Enrollment::with('course:id,title,code')->where('student_id', $student->id)
            ->whereIn('status', ['active', 'completed'])->latest('last_activity_at')->limit(3)->get()
            ->map(fn ($e) => ['enrollment_id' => $e->id, 'course' => $e->course?->title, 'code' => $e->course?->code, 'status' => $e->status,
                'progress_percent' => (int) $e->progress_percent, 'deep_link' => 'academy/enrollments/' . $e->id])->values());

        $this->block($out, 'bills_due', in_array($user->user_type, ['student', 'client'], true), function () use ($user) {
            $open = Invoice::open()->where('user_id', $user->id)->get();
            $total = $open->sum(fn ($i) => $i->balanceMinor());

            return ['count' => $open->count(), 'total_kobo' => $total, 'total' => Money::format(Money::major($total)), 'overdue' => $open->filter(fn ($i) => $i->due_date && $i->due_date->lt(today()))->count()];
        });

        $employee = Employee::where('user_id', $user->id)->where('status', 'active')->first();
        $this->block($out, 'leave_balance', (bool) $employee, fn () => app(LeaveService::class)->balances($employee)
            ->map(fn ($b) => ['type' => $b->type->name, 'allowed' => $b->allowed, 'used' => $b->used, 'pending' => $b->pending, 'left' => $b->left])->values());

        $approvals = [];
        if ($user->can('Manage employees')) $approvals['leave'] = LeaveRequest::where('status', 'pending')->count();
        if ($user->can('Approve finance')) $approvals['expenses'] = Expense::where('status', 'submitted')->count();
        if ($user->can('Manage academy')) $approvals['applications'] = Application::where('status', 'submitted')->count();
        if ($approvals) $out['pending_approvals'] = $approvals + ['total' => array_sum($approvals)];

        $out['unread_mail'] = null;   // Qmail: filled in when mail is set up

        return $this->ok($out);
    }

    public function search(Request $request, GlobalSearch $search)
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) return $this->fail('Type at least two characters.', 422, 'too_short');
        $types = array_filter((array) $request->query('types', []));
        $base = rtrim(url('/'), '/');

        $rows = $search->search($request->user(), $q, min(20, max(1, $request->integer('per_type', 5))))
            ->when($types, fn ($c) => $c->filter(fn ($r) => in_array(Str::slug($r['category']), $types, true)))
            ->map(fn ($r) => [
                'type' => Str::slug($r['category']), 'category' => $r['category'], 'title' => $r['title'], 'subtitle' => $r['subtitle'] ?? null,
                'url' => $r['url'], 'deep_link' => ltrim(Str::after($r['url'], $base), '/'),
            ]);

        return $this->ok(['query' => $q, 'groups' => $rows->groupBy('type')->map(fn ($g, $type) => ['type' => $type, 'category' => $g->first()['category'], 'results' => $g->values()])->values()]);
    }

    /** Add a block only for people who can see it; one failing block never breaks the screen. */
    protected function block(array &$out, string $key, bool $allowed, callable $make): void
    {
        if (!$allowed) return;
        try {
            $out[$key] = $make();
        } catch (\Throwable $e) {
            report($e);
            $out[$key] = null;
        }
    }
}
