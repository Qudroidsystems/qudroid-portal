<?php

namespace App\Services\Reports;

use App\Models\Academy\Application;
use App\Models\Academy\Cohort;
use App\Models\Academy\Enrollment;
use App\Models\Crm\Deal;
use App\Models\Finance\Expense;
use App\Models\Finance\Invoice;
use App\Models\Finance\Payment;
use App\Models\Hr\Employee;
use App\Models\Hr\LeaveRequest;
use App\Models\Hr\PayrollRun;
use App\Models\Inventory\Asset;
use App\Models\Inventory\Item;
use App\Models\Inventory\PurchaseOrder;
use App\Models\Projects\Project;
use App\Models\Projects\Task;
use App\Services\Finance\LedgerService;
use App\Support\Money;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The figures behind the Command Center: one period (this month, quarter …)
 * compared with the one before, across every module. Money is in minor units.
 */
class CommandCenter
{
    public const PERIODS = ['month' => 'This month', 'last_month' => 'Last month', 'quarter' => 'This quarter', 'year' => 'This year'];

    /** When set, every figure is for this business unit only. */
    protected ?int $unit = null;

    public function __construct(protected LedgerService $ledger) {}

    /** @return array{0: CarbonImmutable, 1: CarbonImmutable, 2: CarbonImmutable, 3: CarbonImmutable} from, to, previous from, previous to */
    public function period(string $key): array
    {
        $now = CarbonImmutable::now();
        [$from, $to] = match ($key) {
            'last_month' => [$now->subMonthNoOverflow()->startOfMonth(), $now->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$now->startOfQuarter(), $now->endOfDay()],
            'year' => [$now->startOfYear(), $now->endOfDay()],
            default => [$now->startOfMonth(), $now->endOfDay()],
        };
        // the same length of time just before
        [$pFrom, $pTo] = match ($key) {
            'last_month' => [$from->subMonthNoOverflow()->startOfMonth(), $from->subMonthNoOverflow()->endOfMonth()],
            'quarter' => [$from->subQuarter(), $to->subQuarterNoOverflow()],
            'year' => [$from->subYear(), $to->subYearNoOverflow()],
            default => [$from->subMonthNoOverflow(), $to->subMonthNoOverflow()],
        };

        return [$from, $to, $pFrom, $pTo];
    }

    public function build(string $periodKey, ?int $unit = null): array
    {
        $periodKey = array_key_exists($periodKey, self::PERIODS) ? $periodKey : 'month';
        [$from, $to, $pFrom, $pTo] = $this->period($periodKey);
        $this->unit = $unit;

        return [
            'key' => $periodKey, 'from' => $from, 'to' => $to, 'prevFrom' => $pFrom, 'prevTo' => $pTo, 'unit' => $unit,
            'units' => $unit ? collect() : $this->byUnit($from, $to),
            'finance' => $this->finance($from, $to, $pFrom, $pTo),
            'academy' => $this->academy($from, $to, $pFrom, $pTo),
            'sales' => $this->sales($from, $to, $pFrom, $pTo),
            'projects' => $this->projects(),
            'people' => $this->people(),
            'inventory' => $this->inventory(),
            'trend' => $this->trend(),
        ];
    }

    // ── Sections ────────────────────────────────────────────────────────

    protected function finance($from, $to, $pFrom, $pTo): array
    {
        $collected = fn ($a, $b) => $this->sum($this->payments()->whereBetween('confirmed_at', [$a, $b]), 'amount');
        $spent = fn ($a, $b) => $this->sum($this->own(Expense::where('status', 'paid'))->whereBetween('paid_at', [$a, $b]), 'amount');
        $invoiced = fn ($a, $b) => $this->sum($this->own(Invoice::where('status', '!=', 'void'))->whereBetween('issue_date', [$a->toDateString(), $b->toDateString()]), 'total');

        $open = $this->own(Invoice::open())->get(['total', 'amount_paid', 'due_date', 'status']);
        $overdue = $open->filter->isOverdue();
        // The ledger isn't split by unit; for one unit, profit is cash in less expenses paid.
        $pl = $this->unit ? null : $this->ledger->profitAndLoss(\Illuminate\Support\Carbon::instance($from), \Illuminate\Support\Carbon::instance($to));

        return [
            'collected' => $collected($from, $to), 'collectedPrev' => $collected($pFrom, $pTo),
            'spent' => $spent($from, $to), 'spentPrev' => $spent($pFrom, $pTo),
            'invoiced' => $invoiced($from, $to), 'invoicedPrev' => $invoiced($pFrom, $pTo),
            'ledger' => $pl !== null,
            'income' => (int) ($pl['totalIncome'] ?? 0), 'expenses' => (int) ($pl['totalExpenses'] ?? 0),
            'net' => $pl !== null ? (int) $pl['net'] : $collected($from, $to) - $spent($from, $to),
            'outstanding' => $open->sum(fn ($i) => $i->balanceMinor()), 'openCount' => $open->count(),
            'overdue' => $overdue->sum(fn ($i) => $i->balanceMinor()), 'overdueCount' => $overdue->count(),
            'expensesWaiting' => $this->own(Expense::where('status', 'submitted'))->count(),
            'expensesToPay' => $this->own(Expense::where('status', 'approved'))->count(),
        ];
    }

    protected function academy($from, $to, $pFrom, $pTo): array
    {
        $new = fn ($a, $b) => $this->viaCourse(Enrollment::whereNotIn('status', ['cancelled']))->whereBetween('enrolled_at', [$a, $b])->count();
        $apps = fn ($a, $b) => $this->viaCourse(Application::query())->whereBetween('created_at', [$a, $b])->count();

        return [
            'enrolled' => $new($from, $to), 'enrolledPrev' => $new($pFrom, $pTo),
            'applications' => $apps($from, $to), 'applicationsPrev' => $apps($pFrom, $pTo),
            'active' => $this->viaCourse(Enrollment::where('status', 'active'))->count(),
            'awaitingPayment' => $this->viaCourse(Enrollment::where('status', 'pending'))->count(),
            'toReview' => $this->viaCourse(Application::where('status', 'submitted'))->count(),
            'cohorts' => $this->viaCourse(Cohort::with('course'))->withCount(['enrollments as seats' => fn ($q) => $q->whereIn('status', Enrollment::HOLDS_SEAT)])
                ->whereNotIn('status', ['cancelled', 'completed', 'closed'])->whereDate('starts_on', '>=', today()->subDays(7))
                ->orderBy('starts_on')->limit(5)->get(),
        ];
    }

    protected function sales($from, $to, $pFrom, $pTo): array
    {
        $won = fn ($a, $b) => $this->sum($this->own(Deal::where('stage', 'won'))->whereBetween('won_at', [$a, $b]), 'value');
        $open = $this->own(Deal::open())->get(['stage', 'value', 'expected_close_on']);
        $stages = collect(Deal::OPEN)->map(fn ($s) => [
            'stage' => $s, 'label' => Deal::STAGES[$s][0],
            'count' => $open->where('stage', $s)->count(),
            'value' => $open->where('stage', $s)->sum(fn ($d) => Money::minor($d->value)),
        ]);

        return [
            'won' => $won($from, $to), 'wonPrev' => $won($pFrom, $pTo),
            'wonCount' => $this->own(Deal::where('stage', 'won'))->whereBetween('won_at', [$from, $to])->count(),
            'lostCount' => $this->own(Deal::where('stage', 'lost'))->whereBetween('lost_at', [$from, $to])->count(),
            'pipeline' => $open->sum(fn ($d) => Money::minor($d->value)),
            'weighted' => (int) round($open->sum(fn ($d) => Money::minor($d->value) * (Deal::STAGES[$d->stage][1] ?? 0) / 100)),
            'openCount' => $open->count(),
            'late' => $open->filter(fn ($d) => $d->expected_close_on && $d->expected_close_on->lt(today()))->count(),
            'stages' => $stages,
        ];
    }

    protected function projects(): array
    {
        $active = $this->own(Project::open())->withCount(['tasks', 'tasks as done_count' => fn ($q) => $q->where('status', 'done'),
            'tasks as overdue_count' => fn ($q) => $q->open()->whereDate('due_on', '<', today())])
            ->orderByRaw('due_on is null')->orderBy('due_on')->get();

        return [
            'active' => $active->count(),
            'late' => $active->filter(fn ($p) => $p->due_on && $p->due_on->lt(today()))->count(),
            'overdueTasks' => Task::open()->whereDate('due_on', '<', today())->whereHas('project', fn ($q) => $this->own($q->open()))->count(),
            'list' => $active->take(6),
        ];
    }

    protected function people(): array
    {
        $lastRun = PayrollRun::where('status', 'paid')->latest('paid_at')->first();

        return [
            'headcount' => $this->own(Employee::where('status', 'active'))->count(),
            'joined' => $this->own(Employee::where('status', 'active'))->whereDate('start_date', '>=', today()->startOfMonth())->count(),
            'onLeave' => $this->viaEmployee(LeaveRequest::where('status', 'approved'))->whereDate('starts_on', '<=', today())->whereDate('ends_on', '>=', today())->count(),
            'leaveWaiting' => $this->viaEmployee(LeaveRequest::where('status', 'pending'))->count(),
            'payrollWaiting' => PayrollRun::whereIn('status', ['draft', 'approved'])->count(),
            'lastRun' => $lastRun,
        ];
    }

    protected function inventory(): array
    {
        $low = Item::where('is_active', true)->where('reorder_level', '>', 0)->with('stock')->get()->filter->isLow();

        return [
            'low' => $low->count(),
            'lowList' => $low->take(5),
            'assets' => Asset::whereNotIn('status', ['retired', 'lost'])->count(),
            'assetValue' => $this->sum(Asset::whereNotIn('status', ['retired', 'lost']), 'purchase_cost'),
            'ordersOpen' => PurchaseOrder::whereIn('status', ['ordered', 'partial'])->count(),
        ];
    }

    /** Money in and out for each of the last 12 months. */
    protected function trend(): Collection
    {
        return collect(range(11, 0))->map(function ($i) {
            $m = CarbonImmutable::now()->startOfMonth()->subMonthsNoOverflow($i);
            $end = $m->endOfMonth();

            return [
                'label' => $m->format('M y'),
                'in' => $this->sum($this->payments()->whereBetween('confirmed_at', [$m, $end]), 'amount'),
                'out' => $this->sum($this->own(Expense::where('status', 'paid'))->whereBetween('paid_at', [$m, $end]), 'amount'),
            ];
        });
    }

    /** Things waiting on someone, most pressing first. Each: [count, text, route, tone]. */
    public function attention(array $d): Collection
    {
        return collect([
            [$d['finance']['overdueCount'], 'overdue invoices (' . Money::format(Money::major($d['finance']['overdue'])) . ')', 'finance.invoices.index', 'danger'],
            [$d['people']['payrollWaiting'], 'payroll runs not yet paid', 'hr.payroll.index', 'warning'],
            [$d['finance']['expensesWaiting'], 'expenses waiting for approval', 'finance.expenses.index', 'warning'],
            [$d['finance']['expensesToPay'], 'approved expenses to pay', 'finance.expenses.index', 'info'],
            [$d['projects']['overdueTasks'], 'overdue project tasks', 'projects.index', 'warning'],
            [$d['projects']['late'], 'projects past their due date', 'projects.index', 'danger'],
            [$d['academy']['toReview'], 'academy applications to review', 'academy.applications.index', 'info'],
            [$d['people']['leaveWaiting'], 'leave requests to decide', 'hr.leave.index', 'info'],
            [$d['sales']['late'], 'deals past their expected close date', 'crm.deals.index', 'warning'],
            [$d['inventory']['low'], 'stock items running low', 'inventory.items.index', 'warning'],
        ])->filter(fn ($r) => $r[0] > 0)->values();
    }

    /** Percent change, or null when there's nothing to compare with. */
    public static function change(int|float $now, int|float $before): ?float
    {
        if ($before == 0) return null;

        return round(($now - $before) / abs($before) * 100, 1);
    }

    // ── Business units ──────────────────────────────────────────────────

    /** Each unit side by side for the period, with a combined total. */
    protected function byUnit($from, $to): Collection
    {
        $units = \App\Models\BusinessUnit::ordered()->get();
        if ($units->count() < 2) return collect();

        $group = fn ($q, string $col) => $q->selectRaw('business_unit_id as u, sum(' . $col . ' * 100) as total')->groupBy('business_unit_id')->pluck('total', 'u');
        $count = fn ($q) => $q->selectRaw('business_unit_id as u, count(*) as total')->groupBy('business_unit_id')->pluck('total', 'u');

        $collected = DB::table('finance_payments as p')->where('p.status', 'confirmed')->whereBetween('p.confirmed_at', [$from, $to])
            ->selectRaw('(' . $this->paymentUnitSql() . ') as u, sum(p.amount * 100) as total')->groupBy('u')->pluck('total', 'u');
        $spent = $group(Expense::where('status', 'paid')->whereBetween('paid_at', [$from, $to]), 'amount');
        $owed = Invoice::open()->get(['business_unit_id', 'total', 'amount_paid', 'status'])->groupBy('business_unit_id')->map(fn ($g) => $g->sum(fn ($i) => $i->balanceMinor()));
        $pipeline = $group(Deal::open(), 'value');
        $won = $group(Deal::where('stage', 'won')->whereBetween('won_at', [$from, $to]), 'value');
        $projects = $count(Project::open());
        $staff = $count(Employee::where('status', 'active'));
        $enrolled = Enrollment::join('academy_courses as c', 'c.id', '=', 'academy_enrollments.course_id')->where('academy_enrollments.status', '!=', 'cancelled')
            ->whereBetween('academy_enrollments.enrolled_at', [$from, $to])->selectRaw('c.business_unit_id as u, count(*) as total')->groupBy('c.business_unit_id')->pluck('total', 'u');

        $row = fn ($key, $name, $code, $color) => [
            'id' => $key, 'name' => $name, 'code' => $code, 'color' => $color,
            'collected' => (int) round($collected[$key] ?? 0), 'spent' => (int) round($spent[$key] ?? 0), 'owed' => (int) ($owed[$key] ?? 0),
            'pipeline' => (int) round($pipeline[$key] ?? 0), 'won' => (int) round($won[$key] ?? 0),
            'projects' => (int) ($projects[$key] ?? 0), 'staff' => (int) ($staff[$key] ?? 0), 'enrolled' => (int) ($enrolled[$key] ?? 0),
        ];
        $rows = $units->map(fn ($u) => $row($u->id, $u->name, $u->code, $u->color));
        $none = $row('', 'Not assigned', '—', '#898781');
        if (collect($none)->only(['collected', 'spent', 'owed', 'pipeline', 'won', 'projects', 'staff', 'enrolled'])->sum() > 0) $rows->push($none);

        $total = ['id' => null, 'name' => 'All units', 'code' => '', 'color' => null];
        foreach (['collected', 'spent', 'owed', 'pipeline', 'won', 'projects', 'staff', 'enrolled'] as $k) $total[$k] = $rows->sum($k);
        $rows->push($total);

        return $rows;
    }

    /** SQL for a payment's unit: its invoice's, or (for a payment spread over invoices) the first allocated invoice's. */
    protected function paymentUnitSql(): string
    {
        return 'coalesce((select i.business_unit_id from finance_invoices i where i.id = p.invoice_id), '
            . '(select i2.business_unit_id from finance_payment_allocations a join finance_invoices i2 on i2.id = a.invoice_id where a.payment_id = p.id order by a.id limit 1))';
    }

    /** Confirmed payments, for the unit when one is chosen. */
    protected function payments()
    {
        $q = Payment::where('status', 'confirmed');
        if (!$this->unit) return $q;

        return $q->whereRaw('(' . str_replace('p.', 'finance_payments.', $this->paymentUnitSql()) . ') = ?', [$this->unit]);
    }

    protected function own($q)
    {
        return $this->unit ? $q->where($q->getModel()->getTable() . '.business_unit_id', $this->unit) : $q;
    }

    protected function viaCourse($q)
    {
        return $this->unit ? $q->whereHas('course', fn ($c) => $c->where('business_unit_id', $this->unit)) : $q;
    }

    protected function viaEmployee($q)
    {
        return $this->unit ? $q->whereHas('employee', fn ($e) => $e->where('business_unit_id', $this->unit)) : $q;
    }

    protected function sum($query, string $column): int
    {
        // sums the decimal column exactly, as kobo
        return (int) round(((float) $query->sum(DB::raw($column . ' * 100'))));
    }
}
