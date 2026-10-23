@extends('layouts.master')

@section('content')
@php
    $m = fn (int $minor) => \App\Support\Money::format(\App\Support\Money::major($minor));
    $f = $d['finance']; $a = $d['academy']; $s = $d['sales']; $p = $d['projects']; $h = $d['people']; $i = $d['inventory'];
    $vs = match ($d['key']) { 'last_month' => 'the month before', 'quarter' => 'last quarter', 'year' => 'last year', default => 'last month' };
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Command Center" icon="ri-dashboard-3-line"
        subtitle="{{ $unit ? $unit->name : 'The whole of Qudroid' }} at a glance: {{ $d['from']->format('j M') }} – {{ $d['to']->format('j M Y') }}, compared with {{ $vs }}." />

    <div class="d-flex flex-wrap gap-2 mb-3 align-items-center">
        <div class="d-flex flex-wrap gap-2" role="group" aria-label="Period">
            @foreach($periods as $key => $label)
                <a href="{{ route('command-center', array_filter(['period' => $key, 'unit' => $unit?->id])) }}" class="btn btn-sm {{ $d['key'] === $key ? 'btn-primary' : 'btn-soft-secondary' }}">{{ $label }}</a>
            @endforeach
        </div>
        @if($unitList->isNotEmpty())
            <div class="d-flex flex-wrap gap-2 ms-md-auto" role="group" aria-label="Business unit">
                <a href="{{ route('command-center', ['period' => $d['key']]) }}" class="btn btn-sm {{ !$unit ? 'btn-dark' : 'btn-soft-secondary' }}">All units</a>
                @foreach($unitList as $u)
                    <a href="{{ route('command-center', ['period' => $d['key'], 'unit' => $u->id]) }}" class="btn btn-sm {{ $unit?->id === $u->id ? 'btn-dark' : 'btn-soft-secondary' }}"><span class="cc-dot" style="background: {{ $u->color }}"></span>{{ $u->name }}</a>
                @endforeach
            </div>
        @endif
    </div>

    <div class="cc-grid mb-3">
        @include('command-center._tile', ['label' => 'Cash collected', 'icon' => 'ri-hand-coin-line', 'value' => $m($f['collected']), 'now' => $f['collected'], 'before' => $f['collectedPrev']])
        @include('command-center._tile', ['label' => 'Expenses paid', 'icon' => 'ri-shopping-bag-3-line', 'value' => $m($f['spent']), 'now' => $f['spent'], 'before' => $f['spentPrev'], 'goodWhenUp' => false])
        @if($f['ledger'])
            @include('command-center._tile', ['label' => 'Profit (ledger)', 'icon' => 'ri-line-chart-line', 'value' => $m($f['net']), 'sub' => 'Income ' . $m($f['income']) . ' · costs ' . $m($f['expenses'])])
        @else
            @include('command-center._tile', ['label' => 'Cash result', 'icon' => 'ri-line-chart-line', 'value' => $m($f['net']), 'sub' => 'Collected less expenses paid'])
        @endif
        @include('command-center._tile', ['label' => 'Owed to us', 'icon' => 'ri-file-list-3-line', 'value' => $m($f['outstanding']), 'sub' => $f['openCount'] . ' open invoices · ' . $m($f['overdue']) . ' overdue'])
        @include('command-center._tile', ['label' => 'New enrolments', 'icon' => 'ri-graduation-cap-line', 'value' => number_format($a['enrolled']), 'now' => $a['enrolled'], 'before' => $a['enrolledPrev'], 'sub' => number_format($a['active']) . ' students active now'])
        @include('command-center._tile', ['label' => 'Deals won', 'icon' => 'ri-trophy-line', 'value' => $m($s['won']), 'now' => $s['won'], 'before' => $s['wonPrev'], 'sub' => $s['wonCount'] . ' won · ' . $s['lostCount'] . ' lost'])
        @include('command-center._tile', ['label' => 'Open pipeline', 'icon' => 'ri-funds-line', 'value' => $m($s['pipeline']), 'sub' => $s['openCount'] . ' deals · ' . $m($s['weighted']) . ' weighted'])
        @include('command-center._tile', ['label' => 'Team', 'icon' => 'ri-team-line', 'value' => number_format($h['headcount']) . ' staff', 'sub' => $h['onLeave'] . ' on leave today' . ($h['lastRun'] ? ' · last payroll ' . $m(\App\Support\Money::minor($h['lastRun']->gross)) : '')])
    </div>

    @if($d['units']->isNotEmpty())
        <x-cb.card title="By business unit" icon="ri-building-2-line" :flush="true">
            <x-slot:tools><span class="small text-muted">{{ $periods[$d['key']] }}</span></x-slot:tools>
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0 cc-units">
                    <thead class="table-light"><tr><th>Unit</th><th class="text-end">Collected</th><th class="text-end">Expenses paid</th><th class="text-end">Cash result</th><th class="text-end">Owed to us</th><th class="text-end">Deals won</th><th class="text-end">Open pipeline</th><th class="text-end">Enrolments</th><th class="text-end">Projects</th><th class="text-end">Staff</th></tr></thead>
                    <tbody>
                        @foreach($d['units'] as $r)
                            <tr class="{{ $r['id'] === null ? 'fw-semibold table-light' : '' }}">
                                <td>@if($r['id'])<a href="{{ route('command-center', ['period' => $d['key'], 'unit' => $r['id']]) }}" class="text-reset"><span class="cc-dot" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}</a>@elseif($r['color'])<span class="cc-dot" style="background: {{ $r['color'] }}"></span>{{ $r['name'] }}@else{{ $r['name'] }}@endif</td>
                                <td class="text-end">{{ $m($r['collected']) }}</td>
                                <td class="text-end">{{ $m($r['spent']) }}</td>
                                <td class="text-end {{ $r['collected'] - $r['spent'] < 0 ? 'text-danger' : '' }}">{{ $m($r['collected'] - $r['spent']) }}</td>
                                <td class="text-end">{{ $m($r['owed']) }}</td>
                                <td class="text-end">{{ $m($r['won']) }}</td>
                                <td class="text-end">{{ $m($r['pipeline']) }}</td>
                                <td class="text-end">{{ $r['enrolled'] }}</td>
                                <td class="text-end">{{ $r['projects'] }}</td>
                                <td class="text-end">{{ $r['staff'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-cb.card>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Money in and out, last 12 months" icon="ri-bar-chart-2-line">
                <x-slot:tools><button type="button" class="btn btn-sm btn-soft-secondary" id="ccTableBtn" aria-expanded="false">Show as table</button></x-slot:tools>
                <div class="cc-legend mb-2"><span><i style="background:var(--cc-in)"></i>Collected</span><span><i style="background:var(--cc-out)"></i>Expenses paid</span></div>
                <div id="ccTrend" style="min-height:280px" role="img" aria-label="Bar chart of money collected and expenses paid in each of the last 12 months"></div>
                <div id="ccTable" class="table-responsive d-none">
                    <table class="table table-sm mb-0">
                        <thead><tr><th>Month</th><th class="text-end">Collected</th><th class="text-end">Expenses paid</th><th class="text-end">Difference</th></tr></thead>
                        <tbody>@foreach($d['trend'] as $t)<tr><td>{{ $t['label'] }}</td><td class="text-end">{{ $m($t['in']) }}</td><td class="text-end">{{ $m($t['out']) }}</td><td class="text-end">{{ $m($t['in'] - $t['out']) }}</td></tr>@endforeach</tbody>
                    </table>
                </div>
            </x-cb.card>

            <div class="row g-3">
                <div class="col-md-6">
                    <x-cb.card title="Sales pipeline" icon="ri-funds-line" :flush="true">
                        <x-slot:tools><a href="{{ route('crm.deals.index') }}" class="btn btn-sm btn-soft-primary">Deals</a></x-slot:tools>
                        @php $max = max(1, $s['stages']->max('value')); @endphp
                        @foreach($s['stages'] as $st)
                            <div class="cc-row">
                                <span class="cc-row-label">{{ $st['label'] }} <span class="text-muted">· {{ $st['count'] }}</span></span>
                                <span class="cc-bar"><span style="width: {{ $st['value'] ? max(2, round($st['value'] / $max * 100)) : 0 }}%"></span></span>
                                <span class="cc-row-value">{{ $m($st['value']) }}</span>
                            </div>
                        @endforeach
                    </x-cb.card>
                </div>
                <div class="col-md-6">
                    <x-cb.card title="Upcoming cohorts" icon="ri-graduation-cap-line" :flush="true">
                        <x-slot:tools><a href="{{ route('academy.index') }}" class="btn btn-sm btn-soft-primary">Academy</a></x-slot:tools>
                        @forelse($a['cohorts'] as $c)
                            <div class="cc-row">
                                <span class="cc-row-label text-truncate">{{ $c->name }}<div class="small text-muted">{{ $c->course?->title }} · starts {{ $c->starts_on?->format('j M') }}</div></span>
                                <span class="cc-row-value">{{ $c->seats }}{{ $c->capacity ? ' / ' . $c->capacity : '' }} seats</span>
                            </div>
                        @empty
                            <div class="text-muted small p-3">No cohorts starting soon.</div>
                        @endforelse
                        <div class="small text-muted px-3 pb-3 pt-1">{{ $a['applications'] }} applications this period · {{ $a['awaitingPayment'] }} enrolments awaiting payment</div>
                    </x-cb.card>
                </div>
            </div>

            <x-cb.card title="Active projects" icon="ri-layout-column-line" :count="$p['active']" :flush="true">
                <x-slot:tools><a href="{{ route('projects.index') }}" class="btn btn-sm btn-soft-primary">Projects</a></x-slot:tools>
                @forelse($p['list'] as $pr)
                    @php $pct = $pr->tasks_count ? round($pr->done_count / $pr->tasks_count * 100) : 0; $late = $pr->due_on && $pr->due_on->lt(today()); @endphp
                    <a href="{{ route('projects.show', $pr) }}" class="cc-row text-reset">
                        <span class="cc-row-label text-truncate">{{ $pr->name }}<div class="small {{ $late ? 'text-danger' : 'text-muted' }}">{{ $pr->due_on ? ($late ? 'Was due ' : 'Due ') . $pr->due_on->format('j M') : 'No due date' }}@if($pr->overdue_count) · {{ $pr->overdue_count }} overdue tasks @endif</div></span>
                        <span class="cc-bar"><span style="width: {{ $pct }}%"></span></span>
                        <span class="cc-row-value">{{ $pct }}%</span>
                    </a>
                @empty
                    <div class="text-muted small p-3">No active projects.</div>
                @endforelse
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            <x-cb.card title="Needs attention" icon="ri-alarm-warning-line" :count="$attention->count()" :flush="true">
                @forelse($attention as [$count, $text, $route, $tone])
                    <a href="{{ Route::has($route) ? route($route) : '#' }}" class="cc-row text-reset">
                        <span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} cc-count">{{ $count }}</span>
                        <span class="cc-row-label">{{ $text }}</span>
                        <i class="ri-arrow-right-s-line text-muted"></i>
                    </a>
                @empty
                    <div class="p-3 small"><i class="ri-checkbox-circle-line text-success me-1"></i>Nothing is waiting on anyone.</div>
                @endforelse
            </x-cb.card>

            <x-cb.card title="Invoices and spending" icon="ri-wallet-3-line">
                <dl class="cc-dl">
                    <dt>Invoiced this period</dt><dd>{{ $m($f['invoiced']) }}</dd>
                    <dt>Collected</dt><dd>{{ $m($f['collected']) }}</dd>
                    <dt>Overdue</dt><dd class="{{ $f['overdue'] ? 'text-danger' : '' }}">{{ $m($f['overdue']) }} <span class="text-muted">({{ $f['overdueCount'] }})</span></dd>
                    <dt>Expenses waiting</dt><dd>{{ $f['expensesWaiting'] }} to approve · {{ $f['expensesToPay'] }} to pay</dd>
                </dl>
            </x-cb.card>

            <x-cb.card title="People" icon="ri-team-line">
                <dl class="cc-dl">
                    <dt>Active staff</dt><dd>{{ $h['headcount'] }}@if($h['joined']) <span class="text-muted">({{ $h['joined'] }} joined this month)</span>@endif</dd>
                    <dt>On leave today</dt><dd>{{ $h['onLeave'] }}</dd>
                    <dt>Leave to decide</dt><dd>{{ $h['leaveWaiting'] }}</dd>
                    <dt>Last payroll</dt><dd>{{ $h['lastRun'] ? $h['lastRun']->label . ': ' . $m(\App\Support\Money::minor($h['lastRun']->net)) . ' net' : 'None paid yet' }}</dd>
                </dl>
            </x-cb.card>

            @unless($unit)
            <x-cb.card title="Stock and assets" icon="ri-archive-line">
                <dl class="cc-dl">
                    <dt>Assets in use</dt><dd>{{ $i['assets'] }} · {{ $m($i['assetValue']) }}</dd>
                    <dt>Open purchase orders</dt><dd>{{ $i['ordersOpen'] }}</dd>
                    <dt>Running low</dt><dd class="{{ $i['low'] ? 'text-warning' : '' }}">{{ $i['low'] }} items</dd>
                </dl>
                @foreach($i['lowList'] as $it)
                    <div class="small text-muted"><i class="ri-error-warning-line text-warning me-1"></i>{{ $it->name }}: {{ \App\Models\Inventory\Item::qty($it->onHand()) }} {{ $it->unit }} left</div>
                @endforeach
            </x-cb.card>
            @endunless
        </div>
    </div>
</div></div></div>

<style>
    :root { --cc-in: #2a78d6; --cc-out: #eb6834; }
    [data-bs-theme="dark"] { --cc-in: #3987e5; --cc-out: #d95926; }
    .cc-grid { display:grid; grid-template-columns:repeat(auto-fill, minmax(220px, 1fr)); gap:.75rem; }
    .cc-tile { background:var(--vz-card-bg, #fff); border:1px solid var(--vz-border-color, #e9ebec); border-radius:.6rem; padding:.9rem 1rem; min-width:0; }
    .cc-label { font-size:.8rem; color:var(--vz-secondary-color, #878a99); text-transform:uppercase; letter-spacing:.03em; }
    .cc-icon { font-size:1.2rem; color:var(--vz-secondary-color, #878a99); }
    .cc-value { font-size:1.45rem; font-weight:600; margin:.2rem 0; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
    .cc-sub { font-size:.78rem; color:var(--vz-secondary-color, #878a99); }
    .cc-delta { font-weight:600; margin-right:.25rem; }
    .cc-delta.up { color:#0a8a0a; } .cc-delta.down { color:#c43535; }
    .cc-legend { display:flex; gap:1rem; font-size:.8rem; color:var(--vz-secondary-color, #878a99); }
    .cc-legend i { display:inline-block; width:10px; height:10px; border-radius:2px; margin-right:.35rem; vertical-align:-1px; }
    .cc-row { display:flex; align-items:center; gap:.75rem; padding:.55rem 1rem; border-bottom:1px solid var(--vz-border-color, #f0f0f0); min-width:0; }
    .cc-row:last-child { border-bottom:0; }
    a.cc-row:hover { background:var(--vz-light, #f7f8fa); }
    .cc-row-label { flex:1 1 40%; min-width:0; }
    .cc-row-value { flex:0 0 auto; font-variant-numeric:tabular-nums; font-size:.85rem; }
    .cc-bar { flex:1 1 30%; height:8px; background:var(--vz-light, #f1f2f4); border-radius:4px; overflow:hidden; }
    .cc-bar span { display:block; height:100%; background:var(--cc-in); border-radius:4px; }
    .cc-count { min-width:2rem; }
    .cc-dot { display:inline-block; width:9px; height:9px; border-radius:50%; margin-right:.4rem; vertical-align:0; }
    .cc-units td, .cc-units th { white-space:nowrap; font-variant-numeric:tabular-nums; }
    .cc-dl { display:grid; grid-template-columns:auto 1fr; gap:.35rem 1rem; margin:0 0 .5rem; font-size:.875rem; }
    .cc-dl dt { font-weight:400; color:var(--vz-secondary-color, #878a99); }
    .cc-dl dd { margin:0; text-align:right; font-variant-numeric:tabular-nums; }
</style>
@endsection

@push('scripts')
<script src="{{ asset('theme/layouts/assets/libs/apexcharts/apexcharts.min.js') }}"></script>
<script>
(function () {
    const rows = @json($d['trend']);
    const btn = document.getElementById('ccTableBtn'), chartEl = document.getElementById('ccTrend'), table = document.getElementById('ccTable');
    function showTable(on) { table.classList.toggle('d-none', !on); chartEl.classList.toggle('d-none', on); btn.textContent = on ? 'Show as chart' : 'Show as table'; btn.setAttribute('aria-expanded', on); }
    btn.addEventListener('click', () => showTable(table.classList.contains('d-none')));
    if (typeof ApexCharts === 'undefined') { showTable(true); btn.classList.add('d-none'); return; }
    const css = getComputedStyle(document.documentElement);
    const naira = v => '₦' + (v / 100).toLocaleString(undefined, {minimumFractionDigits: 0, maximumFractionDigits: 0});
    new ApexCharts(chartEl, {
        chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'inherit', background: 'transparent' },
        series: [{ name: 'Collected', data: rows.map(r => r.in) }, { name: 'Expenses paid', data: rows.map(r => r.out) }],
        colors: [css.getPropertyValue('--cc-in').trim() || '#2a78d6', css.getPropertyValue('--cc-out').trim() || '#eb6834'],
        plotOptions: { bar: { columnWidth: '55%', borderRadius: 4, borderRadiusApplication: 'end' } },
        stroke: { show: true, width: 2, colors: ['transparent'] },
        dataLabels: { enabled: false }, legend: { show: false },
        xaxis: { categories: rows.map(r => r.label), axisTicks: { show: false } },
        yaxis: { labels: { formatter: v => v >= 1e8 ? '₦' + (v / 1e8).toFixed(1) + 'm' : naira(v) } },
        grid: { strokeDashArray: 3, borderColor: 'rgba(135,138,153,.2)' },
        tooltip: { shared: true, intersect: false, y: { formatter: naira } },
    }).render();
})();
</script>
@endpush
