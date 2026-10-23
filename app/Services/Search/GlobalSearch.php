<?php

namespace App\Services\Search;

use App\Models\Academy\Course;
use App\Models\Academy\Student;
use App\Models\Crm\Company;
use App\Models\Crm\Contact;
use App\Models\Crm\Deal;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Hr\Employee;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Item;
use App\Models\Meetings\Meeting;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Drive\DriveService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * One search across the portal. Each source checks the person may see it,
 * and returns rows of [title, subtitle, url, icon, category].
 */
class GlobalSearch
{
    /** category => [mdi icon, colour] for the search box. */
    public const CATEGORIES = [
        'People' => ['mdi-account', '#2a78d6'], 'Students' => ['mdi-school', '#1baf7a'], 'Courses' => ['mdi-book-open-variant', '#1baf7a'],
        'Projects' => ['mdi-view-column', '#0ea5e9'], 'Tasks' => ['mdi-checkbox-marked-outline', '#0ea5e9'],
        'Clients' => ['mdi-briefcase', '#6250d6'], 'Deals' => ['mdi-handshake', '#6250d6'],
        'Invoices' => ['mdi-file-document-outline', '#eb6834'], 'Payments' => ['mdi-cash', '#eb6834'], 'Expenses' => ['mdi-receipt', '#eb6834'],
        'Staff' => ['mdi-badge-account', '#e87ba4'], 'Stock' => ['mdi-package-variant', '#c98500'], 'Assets' => ['mdi-laptop', '#c98500'],
        'Meetings' => ['mdi-video', '#299cdb'], 'Files' => ['mdi-file', '#3b82f6'], 'Messages' => ['mdi-message-text', '#22c55e'],
    ];

    public function __construct(protected ?User $user = null) {}

    /** @return Collection<int, array{title:string, subtitle:string, url:string, icon:string, category:string}> */
    public function search(User $user, string $term, int $perSource = 5): Collection
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) return collect();
        $this->user = $user;
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\%', '\_'], $term) . '%';

        $out = collect();
        foreach ($this->sources() as $category => [$allowed, $find]) {
            if (!$allowed()) continue;
            try {
                foreach ($find($like, $perSource, $term) as $row) {
                    $out->push($row + ['category' => $category, 'icon' => self::CATEGORIES[$category][0] ?? 'mdi-magnify']);
                }
            } catch (\Throwable $e) {
                report($e);   // one broken source never breaks the search
            }
        }

        return $out;
    }

    protected function can(string ...$perms): bool
    {
        foreach ($perms as $p) {
            try {
                if ($this->user->can($p)) return true;
            } catch (\Throwable $e) {
            }
        }

        return false;
    }

    protected function has(string $route): bool
    {
        return Route::has($route);
    }

    protected function sources(): array
    {
        $u = fn () => $this->user;

        return [
            'People' => [fn () => $this->can('View user') && $this->has('users.overview'), fn ($like, $n) => User::where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like))
                ->orderBy('name')->limit($n)->get()->map(fn ($x) => ['title' => $x->name, 'subtitle' => $x->email . ' · ' . ucfirst((string) $x->user_type), 'url' => route('users.overview', ['id' => $x->id])])],

            'Students' => [fn () => $this->can('View academy', 'Manage academy') && $this->has('academy.students.show'), fn ($like, $n) => Student::with('user')
                ->where(fn ($q) => $q->where('student_no', 'like', $like)->orWhereHas('user', fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->limit($n)->get()->map(fn ($x) => ['title' => $x->user?->name ?? $x->student_no, 'subtitle' => $x->student_no . ' · ' . $x->status, 'url' => route('academy.students.show', $x)])],

            'Courses' => [fn () => $this->can('View academy', 'Manage academy') && $this->has('academy.courses.show'), fn ($like, $n) => Course::where(fn ($q) => $q->where('title', 'like', $like)->orWhere('code', 'like', $like))
                ->orderBy('title')->limit($n)->get()->map(fn ($x) => ['title' => $x->title, 'subtitle' => $x->code . ' · ' . $x->status, 'url' => route('academy.courses.show', $x)])],

            'Projects' => [fn () => $this->can('View projects', 'Manage projects') && $this->has('projects.show'), fn ($like, $n) => Project::visibleTo($u())
                ->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like)->orWhere('client_name', 'like', $like))
                ->orderBy('name')->limit($n)->get()->map(fn ($x) => ['title' => $x->name, 'subtitle' => $x->code . ' · ' . (Project::STATUSES[$x->status] ?? $x->status), 'url' => route('projects.show', $x)])],

            'Tasks' => [fn () => $this->can('View projects', 'Manage projects') && $this->has('projects.tasks.show'), function ($like, $n, $term) use ($u) {
                $q = Task::with('project')->whereHas('project', fn ($p) => $p->visibleTo($u()));
                // "WEB-14" finds that task
                if (preg_match('/^([A-Za-z0-9]+)-(\d+)$/', $term, $m)) {
                    $q->whereHas('project', fn ($p) => $p->where('code', strtoupper($m[1])))->where('number', (int) $m[2]);
                } else {
                    $q->where('title', 'like', $like);
                }

                return $q->latest('id')->limit($n)->get()->map(fn ($x) => ['title' => $x->title, 'subtitle' => $x->key_label . ' · ' . $x->project?->name . ' · ' . (Task::STATUSES[$x->status] ?? $x->status),
                    'url' => route('projects.tasks.show', [$x->project, $x->number])]);
            }],

            'Clients' => [fn () => $this->can('View clients', 'Manage clients') && $this->has('crm.companies.show'), function ($like, $n) {
                $companies = Company::where('name', 'like', $like)->orderBy('name')->limit($n)->get()
                    ->map(fn ($x) => ['title' => $x->name, 'subtitle' => 'Company · ' . (Company::STATUSES[$x->status] ?? $x->status), 'url' => route('crm.companies.show', $x)]);
                $contacts = Contact::with('company')->where(fn ($q) => $q->where('name', 'like', $like)->orWhere('email', 'like', $like)->orWhere('phone', 'like', $like))
                    ->orderBy('name')->limit($n)->get()->map(fn ($x) => ['title' => $x->name, 'subtitle' => 'Contact' . ($x->company ? ' · ' . $x->company->name : ''), 'url' => route('crm.contacts.show', $x)]);

                return $companies->concat($contacts)->take($n);
            }],

            'Deals' => [fn () => $this->can('View clients', 'Manage clients') && $this->has('crm.deals.show'), fn ($like, $n) => Deal::with('company')->where('title', 'like', $like)
                ->latest('id')->limit($n)->get()->map(fn ($x) => ['title' => $x->title, 'subtitle' => $x->stage_label . ' · ' . \App\Support\Money::format($x->value, $x->currency) . ($x->company ? ' · ' . $x->company->name : ''), 'url' => route('crm.deals.show', $x)])],

            'Invoices' => [fn () => $this->can('View finance', 'Manage finance', 'Approve finance') && $this->has('finance.invoices.show'), fn ($like, $n) => Invoice::with('user')
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('title', 'like', $like)->orWhereHas('user', fn ($w) => $w->where('name', 'like', $like)))
                ->latest('id')->limit($n)->get()->map(fn ($x) => ['title' => $x->number . ' · ' . $x->title, 'subtitle' => ($x->user?->name ?? '') . ' · ' . \App\Support\Money::format($x->total) . ' · ' . (Invoice::STATUSES[$x->status] ?? $x->status), 'url' => route('finance.invoices.show', $x)])],

            'Payments' => [fn () => $this->can('View finance', 'Manage finance', 'Approve finance') && $this->has('finance.payments.show'), fn ($like, $n) => Payment::with('user')
                ->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('reference', 'like', $like))
                ->latest('id')->limit($n)->get()->map(fn ($x) => ['title' => $x->number, 'subtitle' => ($x->user?->name ?? '') . ' · ' . \App\Support\Money::format($x->amount) . ' · ' . $x->status, 'url' => route('finance.payments.show', $x)])],

            'Expenses' => [fn () => $this->can('View finance', 'Manage finance', 'Approve finance') && $this->has('finance.expenses.show'), fn ($like, $n) => Expense::where(fn ($q) => $q->where('number', 'like', $like)->orWhere('payee', 'like', $like)->orWhere('description', 'like', $like))
                ->latest('id')->limit($n)->get()->map(fn ($x) => ['title' => $x->number . ' · ' . $x->payee, 'subtitle' => Str::limit($x->description, 60) . ' · ' . \App\Support\Money::format($x->amount), 'url' => route('finance.expenses.show', $x)])],

            'Staff' => [fn () => $this->can('View employees', 'Manage employees') && $this->has('hr.employees.show'), fn ($like, $n) => Employee::with('user')
                ->where(fn ($q) => $q->where('employee_no', 'like', $like)->orWhere('job_title', 'like', $like)->orWhereHas('user', fn ($w) => $w->where('name', 'like', $like)))
                ->limit($n)->get()->map(fn ($x) => ['title' => $x->user?->name ?? $x->employee_no, 'subtitle' => $x->employee_no . ($x->job_title ? ' · ' . $x->job_title : ''), 'url' => route('hr.employees.show', $x)])],

            'Stock' => [fn () => $this->can('View inventory', 'Manage inventory') && $this->has('inventory.items.show'), fn ($like, $n) => Item::where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('name', 'like', $like))
                ->orderBy('name')->limit($n)->get()->map(fn ($x) => ['title' => $x->name, 'subtitle' => $x->sku, 'url' => route('inventory.items.show', $x)])],

            'Assets' => [fn () => $this->can('View inventory', 'Manage inventory') && $this->has('inventory.assets.show'), fn ($like, $n) => Asset::with('holder')
                ->where(fn ($q) => $q->where('tag', 'like', $like)->orWhere('name', 'like', $like)->orWhere('serial_number', 'like', $like))
                ->orderBy('tag')->limit($n)->get()->map(fn ($x) => ['title' => $x->tag . ' · ' . $x->name, 'subtitle' => (Asset::STATUSES[$x->status] ?? $x->status) . ($x->holder ? ' · ' . $x->holder->name : ''), 'url' => route('inventory.assets.show', $x)])],

            'Meetings' => [fn () => $this->can('View meetings', 'Manage meetings') && $this->has('meetings.show'), fn ($like, $n) => Meeting::for($u())->where('title', 'like', $like)
                ->latest('starts_at')->limit($n)->get()->map(fn ($x) => ['title' => $x->title, 'subtitle' => $x->starts_at->format('D j M Y, H:i'), 'url' => route('meetings.show', $x)])],

            'Files' => [fn () => $this->can('Use connect') && $this->has('drive.index'), function ($like, $n, $term) use ($u) {
                $drive = app(DriveService::class);
                [$folders, $files] = $drive->search($u(), $term);

                return $folders->take(2)->map(fn ($x) => ['title' => $x->name, 'subtitle' => 'Folder', 'url' => $drive->url($x)])
                    ->concat($files->take($n)->map(fn ($x) => ['title' => $x->name, 'subtitle' => \App\Models\Drive\File::humanSize($x->size) . ' · ' . $x->updated_at->diffForHumans(), 'url' => $drive->url($x)]))->take($n);
            }],

            'Messages' => [fn () => $this->can('Use connect') && $this->has('connect.show'), fn ($like, $n, $term) => app(ConnectService::class)->search($u(), $term, $n)
                ->map(fn ($m) => ['title' => Str::limit((string) ($m->body ?: $m->attachment_name), 80), 'subtitle' => ($m->user?->name ?? '') . ' in ' . $m->conversation->titleFor($u()) . ' · ' . $m->created_at->diffForHumans(),
                    'url' => route('connect.show', [$m->conversation, 'around' => $m->thread_id ?: $m->id]) . '#m' . ($m->thread_id ?: $m->id)])],
        ];
    }
}
