@extends('layouts.master')

@section('content')
@php $S = \App\Services\Workflows\Steps::TYPES; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="{{ $wf->name }}" icon="ri-history-line" subtitle="Run history. When {{ mb_strtolower($trigger['label'] ?? $wf->trigger) }}." :back="route('workflows.index')" back-label="Workflows">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('workflows.edit', $wf) }}"><i class="ri-pencil-line me-1"></i>Edit</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="d-flex flex-wrap gap-2 mb-3" role="group" aria-label="Show">
        <a href="{{ route('workflows.runs', $wf) }}" class="btn btn-sm {{ !request('status') ? 'btn-primary' : 'btn-soft-secondary' }}">All</a>
        @foreach(\App\Models\Workflows\WorkflowRun::STATUSES as $k => [$label])
            <a href="{{ route('workflows.runs', [$wf, 'status' => $k]) }}" class="btn btn-sm {{ request('status') === $k ? 'btn-primary' : 'btn-soft-secondary' }}">{{ $label }}</a>
        @endforeach
    </div>

    <x-cb.card title="Runs" icon="ri-list-check-3" :count="$runs->total()" :flush="true">
        @forelse($runs as $run)
            <details class="border-top wf-run" {{ $loop->first && $run->status === 'failed' ? 'open' : '' }}>
                <summary class="px-3 py-2 d-flex gap-2 align-items-center flex-wrap">
                    <span class="badge bg-{{ $run->statusColor() }}-subtle text-{{ $run->statusColor() }}">{{ $run->statusLabel() }}</span>
                    <span class="fw-medium flex-grow-1 text-truncate">{{ $run->title ?: 'Scheduled run' }}</span>
                    <span class="small text-muted" title="{{ $run->created_at }}">{{ $run->created_at->diffForHumans() }}</span>
                </summary>
                <div class="px-3 pb-3">
                    @if($run->payload['_url'] ?? null)<div class="small mb-2"><a href="{{ $run->payload['_url'] }}"><i class="ri-external-link-line me-1"></i>Open the record</a></div>@endif
                    <ol class="wf-log list-unstyled mb-2">
                        @foreach((array) $run->log as $l)
                            <li class="{{ $l['ok'] ? '' : 'text-danger' }}">
                                <i class="{{ $S[$l['type']][1] ?? 'ri-checkbox-blank-circle-line' }} me-1"></i>
                                <strong>{{ $S[$l['type']][0] ?? $l['type'] }}</strong>: {{ $l['note'] }}
                                @if(!empty($l['url']))<a href="{{ $l['url'] }}" class="ms-1">Open</a>@endif
                                <span class="text-muted small ms-1">{{ \Illuminate\Support\Carbon::parse($l['at'])->format('j M, g:ia') }}</span>
                            </li>
                        @endforeach
                        @if($run->status === 'waiting')<li class="text-muted"><i class="ri-hourglass-line me-1"></i>Carries on {{ $run->resume_at?->format('D j M, g:ia') }}.</li>@endif
                        @if(!$run->log && $run->status === 'queued')<li class="text-muted">About to start.</li>@endif
                    </ol>
                    @if($run->status === 'failed')
                        <form method="POST" action="{{ route('workflows.runs.retry', $run) }}" class="m-0">@csrf<button class="btn btn-sm btn-soft-danger"><i class="ri-restart-line me-1"></i>Try the failed step again</button></form>
                    @endif
                </div>
            </details>
        @empty
            <div class="p-4 text-center text-muted">No runs{{ request('status') ? ' like that' : ' yet' }}.</div>
        @endforelse
    </x-cb.card>
    <div class="mt-3">{{ $runs->links() }}</div>
</div></div></div>
@endsection

@push('styles')
<style>
    .wf-run summary { cursor: pointer; list-style: none; }
    .wf-run summary::-webkit-details-marker { display: none; }
    .wf-log li { padding: 4px 0; }
</style>
@endpush
