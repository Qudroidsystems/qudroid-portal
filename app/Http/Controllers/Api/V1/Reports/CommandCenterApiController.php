<?php

namespace App\Http\Controllers\Api\V1\Reports;

use App\Http\Controllers\Api\V1\ApiController;
use App\Models\BusinessUnit;
use App\Services\Reports\CommandCenter;
use App\Support\Money;
use Illuminate\Http\Request;

/** The Command Center for the app: the same figures as the web page (CommandCenter::build), money in kobo with short labels. */
class CommandCenterApiController extends ApiController
{
    public function show(Request $request, CommandCenter $center)
    {
        if (!$request->user()->can('View command center')) return $this->fail('You do not have access to the Command Center.', 403, 'forbidden');

        $units = BusinessUnit::ordered()->get();
        $unit = $units->firstWhere('id', $request->integer('unit')) ?: null;
        $d = $center->build((string) $request->query('period', 'month'), $unit?->id);
        $f = $d['finance'];
        $a = $d['academy'];
        $s = $d['sales'];
        $p = $d['projects'];

        $kpi = fn (string $key, string $label, int $value, ?int $prev, string $good, string $group, string $link, bool $money = true) => [
            'key' => $key, 'label' => $label, 'value' => $value, 'value_label' => $money ? self::short($value) : number_format($value),
            'previous' => $prev, 'change_pct' => $prev === null ? null : CommandCenter::change($value, $prev),
            'good_when' => $good, 'group' => $group, 'money' => $money, 'deep_link' => $link,
        ];
        $prevLabel = ['month' => 'vs last month', 'last_month' => 'vs the month before', 'quarter' => 'vs last quarter', 'year' => 'vs last year'][$d['key']];

        return $this->ok([
            'period' => ['key' => $d['key'], 'label' => CommandCenter::PERIODS[$d['key']], 'from' => $d['from']->toDateString(), 'to' => $d['to']->toDateString(),
                'compare_label' => $prevLabel],
            'periods' => collect(CommandCenter::PERIODS)->map(fn ($l, $k) => ['key' => $k, 'label' => $l])->values(),
            'unit' => $unit ? $this->unit($unit) : null,
            'units' => $units->count() > 1 ? $units->map(fn ($u) => $this->unit($u))->values() : [],
            'kpis' => [
                $kpi('collected', 'Collected', $f['collected'], $f['collectedPrev'], 'up', 'finance', 'finance/payments'),
                $kpi('spent', 'Spent', $f['spent'], $f['spentPrev'], 'down', 'finance', 'finance/expenses'),
                $kpi('net', $f['ledger'] ? 'Profit' : 'Cash in less spent', $f['net'], null, 'up', 'finance', 'finance/reports'),
                $kpi('invoiced', 'Invoiced', $f['invoiced'], $f['invoicedPrev'], 'up', 'finance', 'finance/invoices'),
                $kpi('outstanding', 'Owed to us', $f['outstanding'], null, 'down', 'finance', 'finance/invoices'),
                $kpi('overdue', 'Overdue', $f['overdue'], null, 'down', 'finance', 'finance/invoices'),
                $kpi('won', 'Deals won', $s['won'], $s['wonPrev'], 'up', 'sales', 'crm/deals'),
                $kpi('pipeline', 'Pipeline', $s['pipeline'], null, 'up', 'sales', 'crm/deals'),
                $kpi('enrolled', 'New students', $a['enrolled'], $a['enrolledPrev'], 'up', 'academy', 'academy/enrollments', false),
                $kpi('applications', 'Applications', $a['applications'], $a['applicationsPrev'], 'up', 'academy', 'academy/applications', false),
                $kpi('active_projects', 'Active projects', $p['active'], null, 'up', 'projects', 'projects', false),
                $kpi('late_projects', 'Late projects', $p['late'], null, 'down', 'projects', 'projects', false),
                $kpi('headcount', 'Staff', $d['people']['headcount'], null, 'up', 'people', 'hr/employees', false),
                $kpi('low_stock', 'Low stock', $d['inventory']['low'], null, 'down', 'inventory', 'inventory/items', false),
            ],
            'attention' => $center->attention($d)->map(fn ($r) => [
                'level' => ['danger' => 'urgent', 'warning' => 'warning'][$r[3]] ?? 'info',
                'count' => $r[0], 'title' => $r[0] . ' ' . $r[1], 'subtitle' => null,
                'deep_link' => ltrim(parse_url(route($r[2]), PHP_URL_PATH) ?: '', '/'),
            ])->values(),
            'trend' => $d['trend']->map(fn ($t) => $t + ['in_label' => self::short($t['in']), 'out_label' => self::short($t['out'])])->values(),
            'finance' => ['expenses_waiting' => $f['expensesWaiting'], 'expenses_to_pay' => $f['expensesToPay'], 'open_invoices' => $f['openCount'],
                'overdue_invoices' => $f['overdueCount'], 'from_ledger' => $f['ledger']],
            'sales' => ['pipeline' => $s['pipeline'], 'pipeline_label' => self::short($s['pipeline']), 'weighted' => $s['weighted'], 'weighted_label' => self::short($s['weighted']),
                'won_count' => $s['wonCount'], 'lost_count' => $s['lostCount'], 'open' => $s['openCount'], 'late' => $s['late'],
                'stages' => $s['stages']->map(fn ($st) => $st + ['value_label' => self::short($st['value'])])->values()],
            'projects' => ['active' => $p['active'], 'late' => $p['late'], 'overdue_tasks' => $p['overdueTasks'],
                'list' => $p['list']->map(fn ($x) => ['id' => $x->id, 'code' => $x->code, 'name' => $x->name, 'color' => $x->color,
                    'progress' => $x->tasks_count ? (int) round($x->done_count * 100 / $x->tasks_count) : 0, 'overdue_tasks' => (int) $x->overdue_count,
                    'due_on' => $x->due_on?->toDateString(), 'late' => (bool) ($x->due_on && $x->due_on->lt(today()))])->values()],
            'academy' => ['active' => $a['active'], 'awaiting_payment' => $a['awaitingPayment'], 'to_review' => $a['toReview'],
                'cohorts' => $a['cohorts']->map(fn ($c) => ['id' => $c->id, 'name' => $c->name, 'course' => $c->course?->title, 'starts_on' => $c->starts_on?->toDateString(),
                    'seats' => (int) $c->seats, 'capacity' => $c->capacity])->values()],
            'people' => ['headcount' => $d['people']['headcount'], 'joined' => $d['people']['joined'], 'on_leave' => $d['people']['onLeave'],
                'leave_waiting' => $d['people']['leaveWaiting'], 'payroll_waiting' => $d['people']['payrollWaiting']],
            'by_unit' => collect($d['units'])->map(fn ($r) => ['id' => $r['id'] === '' ? null : $r['id'], 'name' => $r['name'], 'code' => $r['code'], 'color' => $r['color'],
                'total' => $r['name'] === 'All units' && $r['id'] === null,
                'collected' => $r['collected'], 'collected_label' => self::short($r['collected']), 'spent' => $r['spent'], 'spent_label' => self::short($r['spent']),
                'owed' => $r['owed'], 'owed_label' => self::short($r['owed']), 'pipeline' => $r['pipeline'], 'pipeline_label' => self::short($r['pipeline']),
                'won_label' => self::short($r['won']), 'projects' => $r['projects'], 'staff' => $r['staff'], 'enrolled' => $r['enrolled']])->values(),
            'web_url' => route('command-center', array_filter(['period' => $d['key'], 'unit' => $unit?->id])),
        ]);
    }

    /** "₦12.5m", "₦450k", "₦980" from kobo. */
    public static function short(int $minor, ?string $currency = null): string
    {
        $symbol = mb_substr(Money::format(0, $currency), 0, 1);
        $n = $minor / 100;
        $sign = $n < 0 ? '-' : '';
        $n = abs($n);
        $text = match (true) {
            $n >= 1e9 => rtrim(rtrim(number_format($n / 1e9, 1), '0'), '.') . 'bn',
            $n >= 1e6 => rtrim(rtrim(number_format($n / 1e6, 1), '0'), '.') . 'm',
            $n >= 1e3 => rtrim(rtrim(number_format($n / 1e3, 1), '0'), '.') . 'k',
            default => number_format($n),
        };

        return $sign . $symbol . $text;
    }

    protected function unit(BusinessUnit $u): array
    {
        return ['id' => $u->id, 'name' => $u->name, 'code' => $u->code, 'color' => $u->color];
    }
}
