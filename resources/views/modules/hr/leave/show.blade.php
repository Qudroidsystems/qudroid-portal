@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php
        $d = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.');
        $mine = $leave->employee->user_id === auth()->id();
        $back = auth()->user()->can('View employees') ? route('hr.leave.index') : route('me.leave');
    @endphp
    <x-cb.hero :title="$leave->type->name . ': ' . $leave->employee->user->name" icon="ri-suitcase-line" :subtitle="$leave->starts_on->format('j M') . ' – ' . $leave->ends_on->format('j M Y') . ', ' . $d($leave->days) . ' working days'" :back="$back" back-label="Leave" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>
                <div class="d-flex justify-content-between flex-wrap gap-2 mb-3">
                    <div>
                        <div class="fw-semibold">{{ $leave->employee->user->name }}</div>
                        <div class="small text-muted">{{ $leave->employee->job_title }}@if($leave->employee->manager) · reports to {{ $leave->employee->manager->name }}@endif</div>
                    </div>
                    <div>@include('modules.hr._pill', ['value' => $leave->status])</div>
                </div>
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted fw-normal">Type</dt><dd class="col-sm-9">{{ $leave->type->name }}@unless($leave->type->is_paid) <span class="badge bg-secondary-subtle text-secondary">unpaid, deducted from salary</span>@endunless</dd>
                    <dt class="col-sm-3 text-muted fw-normal">From</dt><dd class="col-sm-9">{{ $leave->starts_on->format('l j F Y') }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">To</dt><dd class="col-sm-9">{{ $leave->ends_on->format('l j F Y') }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Working days</dt><dd class="col-sm-9">{{ $d($leave->days) }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Reason</dt><dd class="col-sm-9">{{ $leave->reason ?: '—' }}</dd>
                    <dt class="col-sm-3 text-muted fw-normal">Asked</dt><dd class="col-sm-9">{{ $leave->created_at->format('j M Y H:i') }}</dd>
                    @if($leave->decided_at)
                        <dt class="col-sm-3 text-muted fw-normal">Decided</dt><dd class="col-sm-9">{{ $leave->decided_at->format('j M Y H:i') }} by {{ $leave->decider?->name }}@if($leave->decision_note)<div class="text-muted">{{ $leave->decision_note }}</div>@endif</dd>
                    @endif
                </dl>
            </x-cb.card>

            <x-cb.card title="Also away then" icon="ri-group-line" :flush="true">
                @if($overlapping->isEmpty())
                    <div class="p-3 small text-muted">Nobody else in the same team is on approved leave on these days.</div>
                @else
                    <ul class="list-group list-group-flush">
                        @foreach($overlapping as $o)<li class="list-group-item small">{{ $o->employee->user->name }} · {{ $o->type->name }} <span class="text-muted">{{ $o->starts_on->format('j M') }} – {{ $o->ends_on->format('j M') }}</span></li>@endforeach
                    </ul>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            @if($canDecide)
                <x-cb.card title="Decide" icon="ri-check-double-line">
                    <form method="POST" action="{{ route('hr.leave.decide', $leave) }}">
                        @csrf
                        <textarea name="decision_note" class="form-control mb-2" rows="2" maxlength="255" placeholder="Note (needed when declining)"></textarea>
                        <div class="d-flex gap-2">
                            <button name="decision" value="approve" class="btn btn-success flex-fill">Approve</button>
                            <button name="decision" value="reject" class="btn btn-soft-danger flex-fill">Decline</button>
                        </div>
                    </form>
                </x-cb.card>
            @endif

            <x-cb.card title="{{ $leave->employee->user->name }}'s balance" icon="ri-scales-3-line" :flush="true">
                <ul class="list-group list-group-flush">
                    @foreach($balances as $b)
                        @if($b->allowed !== null || $b->used || $b->pending)
                            <li class="list-group-item small d-flex justify-content-between"><span>{{ $b->type->name }}</span><span>{{ $b->left === null ? $d($b->used) . ' taken' : $d($b->left) . ' of ' . $b->allowed . ' left' }}</span></li>
                        @endif
                    @endforeach
                </ul>
            </x-cb.card>

            @if(in_array($leave->status, ['pending', 'approved'], true) && ($mine || auth()->user()->can('Manage employees')))
                <form method="POST" action="{{ route('hr.leave.cancel', $leave) }}" onsubmit="return confirm('Cancel this leave?')">
                    @csrf<button class="btn btn-soft-secondary w-100"><i class="ri-close-line"></i> Cancel this leave</button>
                </form>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
