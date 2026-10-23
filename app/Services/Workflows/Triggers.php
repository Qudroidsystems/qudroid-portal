<?php

namespace App\Services\Workflows;

use App\Models\Academy\Application;
use App\Models\Academy\Enrollment;
use App\Models\Connect\Message;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Inventory\Stock;
use App\Models\Meetings\Meeting;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;

/**
 * Everything a workflow can start from.
 *
 * Each trigger names the model and event it listens to (created, updated, or "scan" for
 * things found by the every-minute check, such as overdue tasks, or "schedule" for times),
 * a `when` test for updates, the fields a condition or a {{placeholder}} can use, and the
 * people on the record a step can send to ("the assignee").
 */
class Triggers
{
    public const GROUPS = [
        'projects' => ['Projects & tasks', 'ri-layout-column-line'],
        'crm' => ['Clients & sales', 'ri-briefcase-4-line'],
        'finance' => ['Money', 'ri-money-dollar-circle-line'],
        'academy' => ['Academy', 'ri-book-open-line'],
        'hr' => ['People', 'ri-team-line'],
        'inventory' => ['Stock', 'ri-archive-line'],
        'meetings' => ['Meetings', 'ri-video-chat-line'],
        'connect' => ['Chat', 'ri-chat-smile-3-line'],
        'schedule' => ['On a schedule', 'ri-time-line'],
    ];

    public const WEEKDAYS = ['1' => 'Monday', '2' => 'Tuesday', '3' => 'Wednesday', '4' => 'Thursday', '5' => 'Friday', '6' => 'Saturday', '7' => 'Sunday'];

    protected static ?array $all = null;

    public static function all(): array
    {
        return self::$all ??= self::build();
    }

    public static function get(?string $key): ?array
    {
        return $key ? (self::all()[$key] ?? null) : null;
    }

    /** The catalogue for the builder's JavaScript (no closures). */
    public static function forBuilder(): array
    {
        $out = [];
        foreach (self::all() as $key => $t) {
            $out[$key] = [
                'label' => $t['label'], 'group' => $t['group'], 'icon' => $t['icon'], 'hint' => $t['hint'] ?? null,
                'fields' => collect($t['fields'])->map(fn ($f, $k) => ['key' => $k, 'label' => $f[0], 'options' => $f[1] ?? null])->values()->all(),
                'people' => collect($t['people'] ?? [])->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values()->all(),
                'config' => $t['config'] ?? [],
                'project' => !empty($t['project']),
            ];
        }

        return $out;
    }

    /** Model class => [event => [trigger keys]] for the model listeners. */
    public static function listeners(): array
    {
        $map = [];
        foreach (self::all() as $key => $t) {
            if (in_array($t['event'], ['created', 'updated', 'saved'], true)) $map[$t['model']][$t['event']][] = $key;
        }

        return $map;
    }

    /** The values a run carries: fields for conditions and placeholders, plus `_people`, `_url`, `_title`. */
    public static function payload(string $key, ?Model $m): array
    {
        $t = self::get($key);
        $p = $t && $m ? ($t['payload'])($m) : [];
        $actor = auth()->user();
        $p['actor.name'] = $actor?->name ?? 'Qudroid';
        $p['today'] = now()->format('j M Y');

        return $p;
    }

    /** Amounts are stored as decimals ("1500.50"). */
    protected static function money($amount, ?string $currency = null): string
    {
        return Money::format($amount ?? 0, $currency);
    }

    protected static function less($a, $b): string
    {
        return Money::major(Money::minor($a ?? 0) - Money::minor($b ?? 0));
    }

    protected static function date($d): ?string
    {
        return $d ? \Illuminate\Support\Carbon::parse($d)->format('j M Y') : null;
    }

    protected static function build(): array
    {
        $task = fn (Task $t) => [
            'task.title' => $t->title, 'task.key' => $t->key_label, 'task.status' => $t->status, 'task.priority' => $t->priority,
            'task.due' => self::date($t->due_on), 'task.assignee' => $t->assignee?->name, 'task.description' => (string) $t->description,
            'project.name' => $t->project?->name, 'project.code' => $t->project?->code,
            '_people' => ['task.assignee' => $t->assignee_id, 'task.reporter' => $t->reporter_id, 'project.manager' => $t->project?->manager_id],
            '_project_id' => $t->project_id,
            '_url' => $t->project ? route('projects.tasks.show', [$t->project, $t->number]) : null, '_title' => $t->key_label . ' ' . $t->title,
        ];
        $taskFields = [
            'task.title' => ['Task title'], 'task.key' => ['Task number'], 'task.status' => ['Status', Task::STATUSES],
            'task.priority' => ['Priority', Project::PRIORITIES], 'task.due' => ['Due date'], 'task.assignee' => ['Assignee'],
            'task.description' => ['Description'], 'project.name' => ['Project'], 'project.code' => ['Project code'],
        ];
        $taskPeople = ['task.assignee' => 'The assignee', 'task.reporter' => 'Whoever added the task', 'project.manager' => 'The project manager'];

        $project = fn (Project $p) => [
            'project.name' => $p->name, 'project.code' => $p->code, 'project.status' => $p->status, 'project.priority' => $p->priority,
            'project.client' => $p->client_name ?: $p->client?->name, 'project.due' => self::date($p->due_on), 'project.manager' => $p->manager?->name,
            '_people' => ['project.manager' => $p->manager_id, 'project.creator' => $p->created_by, 'project.client' => $p->client_id],
            '_project_id' => $p->id, '_url' => route('projects.show', $p), '_title' => $p->code . ' ' . $p->name,
        ];
        $projectFields = [
            'project.name' => ['Project name'], 'project.code' => ['Project code'], 'project.status' => ['Status', Project::STATUSES],
            'project.priority' => ['Priority', Project::PRIORITIES], 'project.client' => ['Client'], 'project.due' => ['Due date'], 'project.manager' => ['Manager'],
        ];
        $projectPeople = ['project.manager' => 'The project manager', 'project.creator' => 'Whoever created it', 'project.client' => 'The client'];

        $deal = fn (Deal $d) => [
            'deal.title' => $d->title, 'deal.stage' => $d->stage, 'deal.value' => self::money($d->value, $d->currency), 'deal.amount' => (string) $d->value,
            'deal.company' => $d->company?->name, 'deal.contact' => $d->contact?->name, 'deal.owner' => $d->owner?->name, 'deal.source' => $d->source,
            'deal.lost_reason' => $d->lost_reason,
            '_people' => ['deal.owner' => $d->owner_id, 'deal.creator' => $d->created_by],
            '_url' => route('crm.deals.show', $d), '_title' => $d->title,
        ];
        $dealFields = [
            'deal.title' => ['Deal'], 'deal.stage' => ['Stage', collect(Deal::STAGES)->map(fn ($s) => $s[0])->all()], 'deal.value' => ['Value'],
            'deal.amount' => ['Value (number)'], 'deal.company' => ['Company'], 'deal.contact' => ['Contact'], 'deal.owner' => ['Owner'],
            'deal.source' => ['Source'], 'deal.lost_reason' => ['Reason lost'],
        ];
        $dealPeople = ['deal.owner' => 'The deal owner', 'deal.creator' => 'Whoever added the deal'];

        $invoice = fn (Invoice $i) => [
            'invoice.number' => $i->number, 'invoice.title' => $i->title, 'invoice.customer' => $i->user?->name, 'invoice.customer_email' => $i->user?->email,
            'invoice.total' => self::money($i->total, $i->currency), 'invoice.amount' => (string) $i->total,
            'invoice.balance' => self::money(self::less($i->total, $i->amount_paid), $i->currency), 'invoice.status' => $i->status, 'invoice.due' => self::date($i->due_date),
            '_people' => ['invoice.customer' => $i->user_id, 'invoice.creator' => $i->created_by],
            '_url' => route('finance.invoices.show', $i), '_title' => $i->number . ' ' . $i->title,
        ];
        $invoiceFields = [
            'invoice.number' => ['Invoice number'], 'invoice.title' => ['Title'], 'invoice.customer' => ['Customer'], 'invoice.customer_email' => ['Customer e-mail'],
            'invoice.total' => ['Total'], 'invoice.amount' => ['Total (number)'], 'invoice.balance' => ['Balance due'], 'invoice.status' => ['Status', Invoice::STATUSES], 'invoice.due' => ['Due date'],
        ];
        $invoicePeople = ['invoice.customer' => 'The customer', 'invoice.creator' => 'Whoever raised it'];

        $leave = fn (LeaveRequest $l) => [
            'leave.employee' => $l->employee?->user?->name, 'leave.type' => $l->type?->name, 'leave.from' => self::date($l->starts_on), 'leave.to' => self::date($l->ends_on),
            'leave.days' => (string) $l->days, 'leave.reason' => $l->reason, 'leave.status' => $l->status, 'leave.note' => $l->decision_note,
            '_people' => ['leave.employee' => $l->employee?->user_id, 'leave.manager' => $l->employee?->manager_id, 'leave.decider' => $l->decided_by],
            '_url' => route('hr.leave.show', $l), '_title' => 'Leave: ' . ($l->employee?->user?->name ?? ''),
        ];
        $leaveFields = [
            'leave.employee' => ['Employee'], 'leave.type' => ['Leave type'], 'leave.from' => ['From'], 'leave.to' => ['To'], 'leave.days' => ['Days'],
            'leave.reason' => ['Reason'], 'leave.status' => ['Status', LeaveRequest::STATUSES], 'leave.note' => ['Decision note'],
        ];
        $leavePeople = ['leave.employee' => 'The employee', 'leave.manager' => 'Their line manager', 'leave.decider' => 'Whoever decided'];

        $changed = fn (string $attr, ?array $to = null) => fn (Model $m) => $m->wasChanged($attr) && ($to === null || in_array($m->{$attr}, $to, true));

        $t = [
            // Projects & tasks
            'task.created' => ['projects', 'A task is added', Task::class, 'created', $task, $taskFields, $taskPeople, 'project' => true],
            'task.status_changed' => ['projects', 'A task moves to a new status', Task::class, 'updated', $task, $taskFields + ['task.previous_status' => ['Previous status', Task::STATUSES]], $taskPeople,
                'when' => $changed('status'), 'extra' => fn (Task $x) => ['task.previous_status' => $x->getOriginal('status')], 'project' => true],
            'task.assigned' => ['projects', 'A task is assigned to someone', Task::class, 'updated', $task, $taskFields, $taskPeople,
                'when' => fn (Task $x) => $x->wasChanged('assignee_id') && $x->assignee_id, 'project' => true],
            'task.overdue' => ['projects', 'A task becomes overdue', Task::class, 'scan', $task, $taskFields, $taskPeople, 'project' => true,
                'hint' => 'Checked through the day. Each task counts once for each due date.'],
            'project.created' => ['projects', 'A project is created', Project::class, 'created', $project, $projectFields, $projectPeople],
            'project.status_changed' => ['projects', 'A project changes status', Project::class, 'updated', $project, $projectFields, $projectPeople, 'when' => $changed('status')],

            // Clients & sales
            'deal.created' => ['crm', 'A deal is added', Deal::class, 'created', $deal, $dealFields, $dealPeople],
            'deal.stage_changed' => ['crm', 'A deal moves stage', Deal::class, 'updated', $deal, $dealFields, $dealPeople, 'when' => $changed('stage')],
            'deal.won' => ['crm', 'A deal is won', Deal::class, 'updated', $deal, $dealFields, $dealPeople, 'when' => $changed('stage', ['won'])],
            'deal.lost' => ['crm', 'A deal is lost', Deal::class, 'updated', $deal, $dealFields, $dealPeople, 'when' => $changed('stage', ['lost'])],
            'contact.created' => ['crm', 'A contact is added', Contact::class, 'created', fn (Contact $c) => [
                'contact.name' => $c->name, 'contact.email' => $c->email, 'contact.phone' => $c->phone, 'contact.job_title' => $c->job_title, 'contact.company' => $c->company?->name,
                '_people' => [], '_url' => $c->company_id ? route('crm.companies.show', $c->company_id) : null, '_title' => $c->name,
            ], ['contact.name' => ['Name'], 'contact.email' => ['E-mail'], 'contact.phone' => ['Phone'], 'contact.job_title' => ['Job title'], 'contact.company' => ['Company']], []],

            // Money
            'invoice.created' => ['finance', 'An invoice is raised', Invoice::class, 'created', $invoice, $invoiceFields, $invoicePeople],
            'invoice.paid' => ['finance', 'An invoice is paid in full', Invoice::class, 'updated', $invoice, $invoiceFields, $invoicePeople, 'when' => $changed('status', ['paid'])],
            'invoice.overdue' => ['finance', 'An invoice becomes overdue', Invoice::class, 'scan', $invoice, $invoiceFields, $invoicePeople,
                'hint' => 'Checked through the day. Each invoice counts once for each due date.'],
            'payment.confirmed' => ['finance', 'A payment is confirmed', Payment::class, 'saved', fn (Payment $p) => [
                'payment.number' => $p->number, 'payment.amount' => self::money($p->amount, $p->currency), 'payment.value' => (string) $p->amount,
                'payment.method' => $p->method, 'payment.reference' => $p->reference, 'payment.payer' => $p->user?->name, 'invoice.number' => $p->invoice?->number,
                '_people' => ['payment.payer' => $p->user_id], '_url' => route('finance.payments.show', $p), '_title' => $p->number,
            ], ['payment.number' => ['Payment number'], 'payment.amount' => ['Amount'], 'payment.value' => ['Amount (number)'], 'payment.method' => ['Method'],
                'payment.reference' => ['Reference'], 'payment.payer' => ['Paid by'], 'invoice.number' => ['Invoice']], ['payment.payer' => 'Whoever paid'],
                'when' => fn (Payment $p) => $p->status === 'confirmed' && ($p->wasRecentlyCreated || $p->wasChanged('status'))],
            'expense.submitted' => ['finance', 'An expense is submitted', Expense::class, 'created', fn (Expense $e) => [
                'expense.number' => $e->number, 'expense.payee' => $e->payee, 'expense.description' => $e->description, 'expense.amount' => self::money($e->amount, $e->currency),
                'expense.value' => (string) $e->amount, 'expense.by' => $e->submitter?->name,
                '_people' => ['expense.by' => $e->submitted_by], '_url' => route('finance.expenses.show', $e), '_title' => $e->number . ' ' . $e->payee,
            ], ['expense.number' => ['Expense number'], 'expense.payee' => ['Payee'], 'expense.description' => ['Description'], 'expense.amount' => ['Amount'],
                'expense.value' => ['Amount (number)'], 'expense.by' => ['Submitted by']], ['expense.by' => 'Whoever submitted it']],

            // Academy
            'application.submitted' => ['academy', 'Someone applies for a course', Application::class, 'created', fn (Application $a) => [
                'application.reference' => $a->reference, 'application.applicant' => $a->student?->user?->name,
                'application.email' => $a->student?->user?->email, 'application.course' => $a->course?->title,
                'application.cohort' => $a->cohort?->name, 'application.experience' => $a->experience_level, 'application.has_laptop' => $a->has_laptop ? 'yes' : 'no',
                '_people' => ['application.applicant' => $a->student?->user_id], '_url' => route('academy.applications.show', $a), '_title' => $a->reference,
            ], ['application.reference' => ['Reference'], 'application.applicant' => ['Applicant'], 'application.email' => ['Applicant e-mail'], 'application.course' => ['Course'],
                'application.cohort' => ['Cohort'], 'application.experience' => ['Experience', Application::EXPERIENCE], 'application.has_laptop' => ['Has a laptop', ['yes' => 'Yes', 'no' => 'No']]],
                ['application.applicant' => 'The applicant']],
            'enrollment.created' => ['academy', 'A student is enrolled', Enrollment::class, 'created', fn (Enrollment $e) => [
                'enrollment.student' => $e->student?->user?->name, 'enrollment.course' => $e->course?->title, 'enrollment.cohort' => $e->cohort?->name,
                'enrollment.status' => $e->status, 'enrollment.fee' => self::money(self::less($e->fee_amount, $e->discount_amount), $e->currency),
                '_people' => ['enrollment.student' => $e->student?->user_id], '_url' => null, '_title' => ($e->student?->user?->name ?? 'Student') . ' enrolled',
            ], ['enrollment.student' => ['Student'], 'enrollment.course' => ['Course'], 'enrollment.cohort' => ['Cohort'], 'enrollment.status' => ['Status', Enrollment::STATUSES], 'enrollment.fee' => ['Fee']],
                ['enrollment.student' => 'The student']],

            // People
            'employee.created' => ['hr', 'A new employee is added', Employee::class, 'created', fn (Employee $e) => [
                'employee.name' => $e->user?->name, 'employee.email' => $e->user?->email, 'employee.number' => $e->employee_no, 'employee.job_title' => $e->job_title,
                'employee.department' => $e->department?->name, 'employee.start' => self::date($e->start_date), 'employee.manager' => $e->manager?->name,
                '_people' => ['employee.self' => $e->user_id, 'employee.manager' => $e->manager_id], '_url' => route('hr.employees.show', $e), '_title' => $e->user?->name,
            ], ['employee.name' => ['Name'], 'employee.email' => ['E-mail'], 'employee.number' => ['Staff number'], 'employee.job_title' => ['Job title'],
                'employee.department' => ['Department'], 'employee.start' => ['Start date'], 'employee.manager' => ['Line manager']],
                ['employee.self' => 'The new employee', 'employee.manager' => 'Their line manager']],
            'leave.requested' => ['hr', 'Someone asks for leave', LeaveRequest::class, 'created', $leave, $leaveFields, $leavePeople],
            'leave.decided' => ['hr', 'A leave request is approved or declined', LeaveRequest::class, 'updated', $leave, $leaveFields, $leavePeople,
                'when' => $changed('status', ['approved', 'rejected'])],

            // Stock
            'stock.low' => ['inventory', 'An item runs low', Stock::class, 'saved', function (Stock $s) {
                $item = $s->item;
                $onHand = (float) Stock::where('item_id', $s->item_id)->sum('quantity');

                return [
                    'item.name' => $item?->name, 'item.sku' => $item?->sku, 'item.on_hand' => rtrim(rtrim(number_format($onHand, 2, '.', ''), '0'), '.'),
                    'item.reorder_level' => (string) $item?->reorder_level, 'item.unit' => $item?->unit, 'item.supplier' => $item?->supplier?->name,
                    '_people' => [], '_url' => $item ? route('inventory.items.show', $item) : null, '_title' => $item?->name,
                ];
            }, ['item.name' => ['Item'], 'item.sku' => ['SKU'], 'item.on_hand' => ['On hand'], 'item.reorder_level' => ['Reorder level'], 'item.unit' => ['Unit'], 'item.supplier' => ['Supplier']], [],
                'when' => function (Stock $s) {
                    $level = (float) ($s->item?->reorder_level ?? 0);
                    if ($level <= 0 || !($s->wasRecentlyCreated || $s->wasChanged('quantity'))) return false;
                    $now = (float) Stock::where('item_id', $s->item_id)->sum('quantity');
                    $before = $now - (float) $s->quantity + (float) ($s->wasRecentlyCreated ? 0 : $s->getOriginal('quantity'));

                    return $now <= $level && $before > $level;
                },
                'hint' => 'When the total on hand drops to the reorder level or below.'],

            // Meetings
            'meeting.scheduled' => ['meetings', 'A meeting is scheduled', Meeting::class, 'created', fn (Meeting $m) => [
                'meeting.title' => $m->title, 'meeting.when' => $m->starts_at?->format('D j M Y, g:ia'), 'meeting.where' => $m->room?->name ?? $m->location ?? ($m->online_url ? 'Online' : null),
                'meeting.organizer' => $m->organizer?->name, 'meeting.agenda' => $m->agenda,
                '_people' => ['meeting.organizer' => $m->organizer_id], '_project_id' => $m->project_id, '_url' => route('meetings.show', $m), '_title' => $m->title,
            ], ['meeting.title' => ['Title'], 'meeting.when' => ['When'], 'meeting.where' => ['Where'], 'meeting.organizer' => ['Organiser'], 'meeting.agenda' => ['Agenda']],
                ['meeting.organizer' => 'The organiser']],

            // Chat: only public channels, so a workflow never reads private chats.
            'message.channel' => ['connect', 'Someone posts in a channel', Message::class, 'created', fn (Message $m) => [
                'message.channel' => $m->conversation?->name, 'message.text' => (string) $m->body, 'message.from' => $m->user?->name,
                '_people' => ['message.from' => $m->user_id], '_conversation_id' => $m->conversation_id,
                '_url' => route('connect.show', $m->conversation_id), '_title' => '#' . ($m->conversation?->name ?? '') . ': ' . mb_strimwidth((string) $m->body, 0, 60, '…'),
            ], ['message.channel' => ['Channel'], 'message.text' => ['Message'], 'message.from' => ['From']], ['message.from' => 'Whoever posted it'],
                'when' => fn (Message $m) => !$m->is_system && !$m->thread_id && $m->body && $m->conversation?->type === 'channel',
                'hint' => 'Public channels only. Add a condition such as "Message contains urgent".'],

            // Schedules
            'schedule.daily' => ['schedule', 'Every day', null, 'schedule', null, [], [], 'config' => ['time' => ['At', 'time', '08:00'], 'weekdays_only' => ['Weekdays only', 'bool', false]]],
            'schedule.weekly' => ['schedule', 'Every week', null, 'schedule', null, [], [], 'config' => ['weekday' => ['On', 'select', '1', self::WEEKDAYS], 'time' => ['At', 'time', '08:00']]],
            'schedule.monthly' => ['schedule', 'Every month', null, 'schedule', null, [], [], 'config' => ['day' => ['On day', 'number', 1, 1, 28], 'time' => ['At', 'time', '08:00']]],
        ];

        $icons = ['projects' => 'ri-task-line', 'crm' => 'ri-hand-coin-line', 'finance' => 'ri-bill-line', 'academy' => 'ri-graduation-cap-line', 'hr' => 'ri-user-add-line',
            'inventory' => 'ri-archive-line', 'meetings' => 'ri-calendar-event-line', 'connect' => 'ri-hashtag', 'schedule' => 'ri-time-line'];

        $out = [];
        foreach ($t as $key => $d) {
            [$group, $label, $model, $event, $payload, $fields, $people] = $d;
            $extra = $d['extra'] ?? null;
            $out[$key] = [
                'group' => $group, 'label' => $label, 'icon' => $icons[$group] ?? 'ri-flashlight-line', 'model' => $model, 'event' => $event,
                'payload' => $payload ? ($extra ? fn ($m) => $payload($m) + $extra($m) : $payload) : fn () => ['_people' => [], '_url' => null, '_title' => null],
                'fields' => $fields, 'people' => $people, 'when' => $d['when'] ?? null, 'hint' => $d['hint'] ?? null,
                'config' => $d['config'] ?? [], 'project' => $d['project'] ?? false,
            ];
        }

        return $out;
    }
}
