@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $m = fn ($minor) => \App\Support\Money::format(\App\Support\Money::major($minor)); @endphp
    <x-cb.hero title="Clients & CRM" icon="ri-briefcase-4-line" subtitle="Companies, contacts, the sales pipeline and your follow-ups.">
        <x-slot:actions>
            <a href="{{ route('crm.deals.index') }}" class="cb-hero-btn"><i class="ri-git-branch-line"></i>Pipeline</a>
            @can('Manage clients')<a href="{{ route('crm.companies.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New company</a>@endcan
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Open pipeline" :value="$m($stats['openValue'])" icon="ri-git-branch-line" accent="teal" :hint="$stats['openCount'] . ' open deals'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Weighted" :value="$m($stats['weighted'])" icon="ri-scales-3-line" accent="sky" hint="value × chance of winning" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Won this month" :value="$m($stats['wonMonth'])" icon="ri-trophy-line" accent="green" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Win rate" :value="$stats['winRate'] === null ? '—' : $stats['winRate'] . '%'" icon="ri-percent-line" accent="amber" hint="deals closed in the last 90 days" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-6">
            <x-cb.card title="My follow-ups" icon="ri-alarm-line" :count="$followUps->count()" :flush="true">
                <x-slot:tools><a href="{{ route('crm.follow-ups') }}" class="btn btn-sm btn-soft-primary">All</a></x-slot:tools>
                @if($followUps->isEmpty())
                    <div class="empty-state"><i class="ri-alarm-line"></i><h6>Nothing to follow up</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($followUps as $f)
                            <li class="list-group-item d-flex gap-2 align-items-start">
                                <form method="POST" action="{{ route('crm.activities.complete', $f) }}">@csrf<button class="btn btn-sm btn-soft-success" title="Done"><i class="ri-check-line"></i></button></form>
                                <div class="flex-grow-1 small">
                                    <div>{{ $f->body }}</div>
                                    <div class="text-muted"><a href="{{ $crm->link($f) }}">{{ $f->subject?->title ?? $f->subject?->name }}</a>@if($f->company && !($f->subject instanceof \App\Models\Crm\Company)) · {{ $f->company->name }}@endif</div>
                                </div>
                                <span class="small text-nowrap {{ $f->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">{{ $f->due_at->format('j M') }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-6">
            <x-cb.card title="Expected to close" icon="ri-calendar-check-line" :flush="true">
                @if($closing->isEmpty())
                    <div class="empty-state"><i class="ri-calendar-check-line"></i><h6>No close dates set</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack"><tbody>
                        @foreach($closing as $d)
                            <tr>
                                <td data-label="Deal"><a href="{{ route('crm.deals.show', $d) }}" class="fw-semibold">{{ $d->title }}</a><div class="small text-muted">{{ $d->company?->name ?? '—' }} · {{ $d->owner?->name }}</div></td>
                                <td data-label="Stage">@include('modules.crm._pill', ['value' => $d->stage])</td>
                                <td data-label="Value" class="num">{{ \App\Support\Money::format($d->value, $d->currency) }}</td>
                                <td data-label="Close" class="text-nowrap {{ $d->isLate() ? 'text-danger fw-semibold' : '' }}">{{ $d->expected_close_on->format('j M') }}</td>
                            </tr>
                        @endforeach
                    </tbody></table></div>
                @endif
            </x-cb.card>

            <x-cb.card title="Latest activity" icon="ri-chat-history-line" :flush="true">
                @if($recent->isEmpty())
                    <div class="empty-state"><i class="ri-chat-history-line"></i><h6>Nothing logged yet</h6></div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($recent as $a)
                            <li class="list-group-item small"><i class="{{ $a->icon }} text-muted"></i> <span class="fw-semibold">{{ $a->user?->name }}</span> · <a href="{{ $crm->link($a) }}">{{ $a->subject?->title ?? $a->subject?->name }}</a>: {{ \Illuminate\Support\Str::limit($a->body, 90) }} <span class="text-muted">· {{ $a->created_at->diffForHumans() }}</span></li>
                        @endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
