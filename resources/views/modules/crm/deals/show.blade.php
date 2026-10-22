@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$deal->title" icon="ri-git-branch-line" :subtitle="\App\Support\Money::format($deal->value, $deal->currency) . ' · ' . $deal->stage_label . ($deal->company ? ' · ' . $deal->company->name : '')" :back="route('crm.deals.index')" back-label="Pipeline" />

    @include('partials.flash')

    @if($deal->isOpen())
        <div class="d-flex flex-wrap gap-1 mb-3">
            @foreach(\App\Models\Crm\Deal::OPEN as $s)
                <span class="badge rounded-pill {{ array_search($s, \App\Models\Crm\Deal::OPEN) <= array_search($deal->stage, \App\Models\Crm\Deal::OPEN) ? 'bg-primary' : 'bg-light text-muted' }} px-3 py-2">{{ \App\Models\Crm\Deal::STAGES[$s][0] }}</span>
            @endforeach
        </div>
    @elseif($deal->stage === 'won')
        <div class="cb-banner success"><i class="ri-trophy-line"></i><div>Won on {{ $deal->won_at?->format('j M Y') }}.
            @if($deal->project)
                Project: <a href="{{ route('projects.show', $deal->project) }}">{{ $deal->project->name }}</a>.
            @else
                @can('Manage projects') <a href="{{ route('projects.create', ['deal' => $deal->id]) }}" class="fw-semibold">Start a project from this deal</a>. @endcan
            @endif
        </div></div>
    @else
        <div class="cb-banner danger"><i class="ri-close-circle-line"></i><div>Lost on {{ $deal->lost_at?->format('j M Y') }}: {{ $deal->lost_reason }}</div></div>
    @endif

    <div class="row g-3">
        <div class="col-xl-4">
            @can('Manage clients')
                @if($deal->isOpen())
                    <x-cb.card title="Close the deal" icon="ri-flag-2-line">
                        <form method="POST" action="{{ route('crm.deals.win', $deal) }}" class="mb-3">@csrf<button class="btn btn-success w-100"><i class="ri-trophy-line"></i> Mark won</button></form>
                        <form method="POST" action="{{ route('crm.deals.lose', $deal) }}">
                            @csrf
                            <input name="reason" class="form-control form-control-sm mb-2" maxlength="255" required placeholder="Why it was lost, e.g. went with a cheaper vendor">
                            <button class="btn btn-soft-danger btn-sm w-100">Mark lost</button>
                        </form>
                    </x-cb.card>
                @else
                    <form method="POST" action="{{ route('crm.deals.reopen', $deal) }}" class="mb-3">@csrf<button class="btn btn-sm btn-soft-secondary"><i class="ri-arrow-go-back-line"></i> Reopen deal</button></form>
                @endif

                <x-cb.card title="Details" icon="ri-settings-3-line">
                    <form method="POST" action="{{ route('crm.deals.update', $deal) }}">
                        @csrf @method('PUT')
                        <label class="form-label small">Title</label>
                        <input name="title" class="form-control form-control-sm mb-2" value="{{ $deal->title }}" maxlength="200" required>
                        <div class="row g-2 mb-2">
                            <div class="col-7"><label class="form-label small">Value</label><input type="number" step="0.01" min="0" name="value" class="form-control form-control-sm" value="{{ $deal->value }}"></div>
                            <div class="col-5"><label class="form-label small">Chance %</label><input type="number" min="0" max="100" name="probability" class="form-control form-control-sm" value="{{ $deal->probability }}"></div>
                        </div>
                        <label class="form-label small">Company</label>
                        <select name="company_id" class="form-select form-select-sm mb-2"><option value="">None</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected($deal->company_id === $c->id)>{{ $c->name }}</option>@endforeach</select>
                        <label class="form-label small">Contact</label>
                        <select name="contact_id" class="form-select form-select-sm mb-2"><option value="">None</option>@foreach($contacts as $c)<option value="{{ $c->id }}" @selected($deal->contact_id === $c->id)>{{ $c->name }}</option>@endforeach</select>
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">Expected close</label><input type="date" name="expected_close_on" class="form-control form-control-sm" value="{{ $deal->expected_close_on?->toDateString() }}"></div>
                            <div class="col-6"><label class="form-label small">Source</label><input name="source" class="form-control form-control-sm" value="{{ $deal->source }}" maxlength="50"></div>
                        </div>
                        <label class="form-label small">Owner</label>
                        <select name="owner_id" class="form-select form-select-sm mb-2"><option value="">Nobody</option>@foreach($staff as $s)<option value="{{ $s->id }}" @selected($deal->owner_id === $s->id)>{{ $s->name }}</option>@endforeach</select>
                        <textarea name="notes" rows="3" class="form-control form-control-sm mb-2" maxlength="5000" placeholder="Notes">{{ $deal->notes }}</textarea>
                        <button class="btn btn-sm btn-primary w-100">Save</button>
                        <div class="form-text">Save after changing the company to pick one of its contacts.</div>
                    </form>
                    <form method="POST" action="{{ route('crm.deals.destroy', $deal) }}" class="mt-2" onsubmit="return confirm('Delete this deal and its timeline?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i> Delete deal</button></form>
                </x-cb.card>
            @else
                <x-cb.card title="Details" icon="ri-information-line">
                    <dl class="row mb-0 small">
                        <dt class="col-5 text-muted fw-normal">Chance</dt><dd class="col-7">{{ $deal->probability }}%</dd>
                        <dt class="col-5 text-muted fw-normal">Contact</dt><dd class="col-7">{{ $deal->contact?->name ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Expected close</dt><dd class="col-7">{{ $deal->expected_close_on?->format('j M Y') ?? '—' }}</dd>
                        <dt class="col-5 text-muted fw-normal">Owner</dt><dd class="col-7">{{ $deal->owner?->name ?? '—' }}</dd>
                    </dl>
                    @if($deal->notes)<div class="small mt-2" style="white-space: pre-line">{{ $deal->notes }}</div>@endif
                </x-cb.card>
            @endcan
        </div>
        <div class="col-xl-8">
            @include('modules.crm._timeline', ['subjectType' => 'deal', 'subjectId' => $deal->id])
        </div>
    </div>

</div></div></div>
@endsection
