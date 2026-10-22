@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero title="Pipeline" icon="ri-git-branch-line" :subtitle="$stats['openCount'] . ' open deals worth ' . $m($stats['openValue']) . ' (' . $m($stats['weighted']) . ' weighted)'" :back="route('crm.index')" back-label="CRM">
        <x-slot:actions>
            <a href="{{ route('crm.deals.index', $list ? [] : ['view' => 'list']) }}" class="cb-hero-btn"><i class="{{ $list ? 'ri-layout-column-line' : 'ri-list-check-2' }}"></i>{{ $list ? 'Board' : 'All deals' }}</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <form class="cb-toolbar cb-card mb-3" method="GET">
        @if($list)<input type="hidden" name="view" value="list">@endif
        <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Deal or company"></div>
        <select name="owner" class="cb-select" onchange="this.form.submit()"><option value="">Everyone's</option><option value="me" @selected(request('owner') === 'me')>Mine</option></select>
        @if($list)
            <select name="stage" class="cb-select" onchange="this.form.submit()"><option value="">Any stage</option>@foreach(\App\Models\Crm\Deal::STAGES as $k => [$v])<option value="{{ $k }}" @selected(request('stage') === $k)>{{ $v }}</option>@endforeach</select>
        @endif
    </form>

    @if($list)
        <x-cb.card :flush="true">
            @if($deals->isEmpty())
                <div class="empty-state"><i class="ri-git-branch-line"></i><h6>No deals</h6></div>
            @else
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Deal</th><th>Company</th><th>Owner</th><th>Stage</th><th>Close</th><th class="num">Value</th></tr></thead>
                    <tbody>
                    @foreach($deals as $d)
                        <tr>
                            <td data-label="Deal"><a href="{{ route('crm.deals.show', $d) }}" class="fw-semibold">{{ $d->title }}</a></td>
                            <td data-label="Company">{{ $d->company?->name ?? '—' }}</td>
                            <td data-label="Owner">{{ $d->owner?->name ?? '—' }}</td>
                            <td data-label="Stage">@include('modules.crm._pill', ['value' => $d->stage])</td>
                            <td data-label="Close" class="{{ $d->isLate() ? 'text-danger fw-semibold' : '' }}">{{ $d->expected_close_on?->format('j M Y') ?? '—' }}</td>
                            <td data-label="Value" class="num">{{ \App\Support\Money::format($d->value, $d->currency) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-cb.card>
    @else
        <div class="pj-board" style="grid-template-columns: repeat(5, minmax(220px, 1fr))">
            @foreach($columns as $stage => $items)
                <div class="pj-col">
                    <div class="pj-col-head">
                        <span>{{ \App\Models\Crm\Deal::STAGES[$stage][0] }} <span class="cb-count">{{ $items->count() }}</span></span>
                        <span class="small text-muted pj-sum">{{ $m($items->sum(fn ($d) => \App\Support\Money::minor($d->value))) }}</span>
                    </div>
                    <div class="pj-list" data-stage="{{ $stage }}">
                        @foreach($items as $d)
                            <a href="{{ route('crm.deals.show', $d) }}" class="pj-card" data-id="{{ $d->id }}" data-value="{{ \App\Support\Money::minor($d->value) }}">
                                <div class="pj-title">{{ $d->title }}</div>
                                <div class="small text-muted">{{ $d->company?->name ?? 'No company' }}</div>
                                <div class="d-flex justify-content-between small mt-1">
                                    <span class="fw-semibold">{{ \App\Support\Money::format($d->value, $d->currency) }}</span>
                                    @if($d->expected_close_on)<span class="{{ $d->isLate() ? 'text-danger fw-semibold' : 'text-muted' }}"><i class="ri-calendar-line"></i> {{ $d->expected_close_on->format('j M') }}</span>@endif
                                </div>
                            </a>
                        @endforeach
                    </div>
                    @can('Manage clients')
                        <form method="POST" action="{{ route('crm.deals.store') }}" class="pj-add">
                            @csrf
                            <input type="hidden" name="stage" value="{{ $stage }}"><input type="hidden" name="back" value="1">
                            <input name="title" class="form-control form-control-sm" maxlength="200" required placeholder="+ Add a deal">
                        </form>
                    @endcan
                </div>
            @endforeach
        </div>
        <div class="small text-muted mt-2">Won and lost deals leave the board; see them under All deals. Mark a deal won or lost from its page.</div>
    @endif

</div></div></div>
@endsection

@push('styles')
<style>
    .pj-board { display: grid; gap: 14px; overflow-x: auto; padding-bottom: 8px; }
    .pj-col { background: var(--cb-surface-2); border: 1px solid var(--cb-border); border-radius: var(--cb-radius); padding: 10px; display: flex; flex-direction: column; min-height: 200px; }
    .pj-col-head { display: flex; justify-content: space-between; align-items: center; font-weight: 600; color: var(--cb-heading); padding: 4px 4px 10px; gap: 6px; }
    .pj-list { flex: 1; display: flex; flex-direction: column; gap: 8px; min-height: 40px; }
    .pj-card { display: block; background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: var(--cb-radius-sm); padding: 10px 12px; color: var(--cb-text); text-decoration: none; cursor: grab; }
    .pj-card:hover { border-color: var(--cb-teal); color: var(--cb-text); }
    .pj-title { font-weight: 600; color: var(--cb-heading); line-height: 1.35; }
    .pj-add { margin-top: 8px; }
    .pj-ghost { opacity: .4; }
    @media (max-width: 1199px) { .pj-board { grid-template-columns: repeat(5, 240px) !important; } }
</style>
@endpush

@if(!$list)
@can('Manage clients')
@push('scripts')
<script src="{{ asset('theme/layouts/assets/libs/sortablejs/Sortable.min.js') }}"></script>
<script>
(function () {
    const moveUrl = @json(route('crm.deals.move', '__ID__'));
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const fmt = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    const totals = () => document.querySelectorAll('.pj-col').forEach(col => {
        const cards = col.querySelectorAll('.pj-card');
        col.querySelector('.cb-count').textContent = cards.length;
        const sum = [...cards].reduce((s, c) => s + (+c.dataset.value || 0), 0) / 100;
        const el = col.querySelector('.pj-sum');
        el.textContent = el.textContent.replace(/[\d.,]+/, fmt.format(sum));
    });
    document.querySelectorAll('.pj-list').forEach(function (list) {
        Sortable.create(list, {
            group: 'deals', animation: 150, ghostClass: 'pj-ghost',
            onEnd: function (e) {
                const order = [...e.to.querySelectorAll('.pj-card')].map(c => +c.dataset.id);
                fetch(moveUrl.replace('__ID__', e.item.dataset.id), {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': token },
                    body: JSON.stringify({ stage: e.to.dataset.stage, order: order }),
                }).then(r => { if (!r.ok) throw r; totals(); })
                  .catch(() => { alert('Could not move the deal. The page will reload.'); location.reload(); });
            },
        });
    });
})();
</script>
@endpush
@endcan
@endif
