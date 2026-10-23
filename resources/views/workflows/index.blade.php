@extends('layouts.master')

@section('content')
@php $T = \App\Services\Workflows\Triggers::all(); $S = \App\Services\Workflows\Steps::TYPES; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Workflows" icon="ri-flow-chart" subtitle="Automations: when something happens in the portal, notify people, send e-mails, post in chat, add tasks or call another app.">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('workflows.create') }}"><i class="ri-add-line me-1"></i>New workflow</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Your workflows" icon="ri-flow-chart" :count="$workflows->count()" :flush="true">
                @forelse($workflows as $wf)
                    @php $t = $T[$wf->trigger] ?? null; @endphp
                    <div class="wf-row border-top p-3 d-flex gap-3 align-items-center flex-wrap">
                        <div class="wf-ico {{ $wf->is_active ? 'on' : '' }}"><i class="{{ $t['icon'] ?? 'ri-flashlight-line' }}"></i></div>
                        <div class="flex-grow-1 min-w-0">
                            <a href="{{ route('workflows.edit', $wf) }}" class="fw-semibold text-body">{{ $wf->name }}</a>
                            <div class="small text-muted">When {{ mb_strtolower($t['label'] ?? $wf->trigger) }}
                                <span class="wf-steps ms-1">@foreach($wf->steps() as $s)<i class="{{ $S[$s['type']][1] ?? 'ri-checkbox-blank-circle-line' }}" title="{{ $S[$s['type']][0] ?? $s['type'] }}"></i>@endforeach</span>
                            </div>
                            <div class="small text-muted">
                                {{ $wf->runs_count }} {{ \Illuminate\Support\Str::plural('run', $wf->runs_count) }}{{ $wf->last_run_at ? ', last ' . $wf->last_run_at->diffForHumans() : '' }}
                                @if($wf->failed_count)<a href="{{ route('workflows.runs', [$wf, 'status' => 'failed']) }}" class="text-danger ms-1"><i class="ri-error-warning-line"></i> {{ $wf->failed_count }} failed this week</a>@endif
                            </div>
                        </div>
                        <form method="POST" action="{{ route('workflows.toggle', $wf) }}" class="m-0">
                            @csrf
                            <button class="btn btn-sm {{ $wf->is_active ? 'btn-success' : 'btn-soft-secondary' }}" aria-pressed="{{ $wf->is_active ? 'true' : 'false' }}" title="{{ $wf->is_active ? 'Switch off' : 'Switch on' }}">
                                <i class="{{ $wf->is_active ? 'ri-toggle-fill' : 'ri-toggle-line' }} me-1"></i>{{ $wf->is_active ? 'On' : 'Off' }}
                            </button>
                        </form>
                        <a href="{{ route('workflows.runs', $wf) }}" class="btn btn-sm btn-soft-primary"><i class="ri-history-line me-1"></i>Runs</a>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">
                        <i class="ri-flow-chart fs-1 d-block mb-2"></i>
                        No workflows yet. Start from a ready-made one below, or <a href="{{ route('workflows.create') }}">build your own</a>.
                    </div>
                @endforelse
            </x-cb.card>

            <x-cb.card title="Start from a ready-made workflow" icon="ri-magic-line" class="mt-3">
                <div class="wf-recipes">
                    @foreach($recipes as $key => $r)
                        <a href="{{ route('workflows.create', ['recipe' => $key]) }}" class="wf-recipe">
                            <i class="{{ $r['icon'] }}"></i>
                            <div><div class="fw-semibold text-body">{{ $r['name'] }}</div><div class="small text-muted">{{ $r['description'] }}</div></div>
                        </a>
                    @endforeach
                </div>
                <div class="small text-muted mt-2">They open in the builder so you can adjust them. Nothing runs until you switch it on.</div>
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Latest runs" icon="ri-history-line" :flush="true">
                @forelse($recent as $run)
                    <a href="{{ route('workflows.runs', $run->workflow_id) }}" class="d-block border-top px-3 py-2 text-body">
                        <div class="d-flex justify-content-between gap-2"><span class="fw-medium text-truncate">{{ $run->workflow?->name }}</span><span class="badge bg-{{ $run->statusColor() }}-subtle text-{{ $run->statusColor() }}">{{ $run->statusLabel() }}</span></div>
                        <div class="small text-muted text-truncate">{{ $run->title ?: 'Scheduled run' }} · {{ $run->created_at->diffForHumans() }}</div>
                    </a>
                @empty
                    <div class="p-3 small text-muted">Runs show here as workflows start.</div>
                @endforelse
            </x-cb.card>
            <x-cb.card title="How it works" icon="ri-question-line" class="mt-3">
                <ol class="small text-muted ps-3 mb-0">
                    <li class="mb-1"><strong>When</strong>: pick what starts it, such as a task becoming overdue or a deal being won.</li>
                    <li class="mb-1"><strong>Only if</strong> (optional): rules such as "Priority is Urgent".</li>
                    <li class="mb-1"><strong>Then</strong>: steps run in order. Use <code>@{{task.title}}</code>-style fields to fill in details, and Wait to pause.</li>
                    <li>Use <strong>Try it</strong> in the builder to see what would happen with a real record, without sending anything.</li>
                </ol>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection

@push('styles')
<style>
    .wf-ico { width: 40px; height: 40px; border-radius: 10px; display: grid; place-items: center; background: var(--vz-light); color: var(--vz-secondary-color); font-size: 20px; flex: none; }
    .wf-ico.on { background: rgba(124, 58, 237, .12); color: #7c3aed; }
    .wf-steps i { margin-right: 4px; opacity: .8; }
    .min-w-0 { min-width: 0; }
    .wf-recipes { display: grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap: 10px; }
    .wf-recipe { display: flex; gap: 12px; padding: 12px; border: 1px solid var(--vz-border-color); border-radius: 10px; transition: border-color .15s, background .15s; }
    .wf-recipe:hover, .wf-recipe:focus { border-color: #7c3aed; background: rgba(124, 58, 237, .04); }
    .wf-recipe > i { font-size: 22px; color: #7c3aed; }
</style>
@endpush
