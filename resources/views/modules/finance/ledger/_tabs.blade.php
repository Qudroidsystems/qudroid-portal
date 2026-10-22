<div class="d-flex flex-wrap gap-2 mb-3">
    @foreach(['finance.ledger.accounts' => 'Chart of accounts', 'finance.ledger.journals' => 'Journal', 'finance.reports.trial-balance' => 'Trial balance', 'finance.reports.profit-loss' => 'Profit and loss'] as $r => $label)
        <a href="{{ route($r) }}" class="btn btn-sm {{ request()->routeIs($r) ? 'btn-primary' : 'btn-soft-secondary' }}">{{ $label }}</a>
    @endforeach
</div>
