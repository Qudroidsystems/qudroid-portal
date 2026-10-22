@extends('layouts.master')

@section('content')
@php $answers = $attempt->answers ?? []; $marks = $attempt->marks ?? []; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$attempt->enrollment->student->user->name" icon="ri-questionnaire-line" :subtitle="$assessment->title . ' · attempt ' . $attempt->attempt_no" :back="route('academy.assessments.results', [$course, $assessment])" back-label="Results">
        <x-slot:pills>
            <span class="cb-meta-pill">Submitted {{ $attempt->submitted_at?->format('j M Y, g:ia') ?? 'not yet' }}</span>
            <span class="cb-meta-pill">{{ $attempt->needs_review ? 'Essays to mark' : 'Score ' . rtrim(rtrim(number_format((float) $attempt->percent, 1), '0'), '.') . '%' }}</span>
            @if($attempt->timed_out)<span class="cb-meta-pill">Ran out of time</span>@endif
        </x-slot:pills>
    </x-cb.hero>

    @include('partials.flash')

    <form method="POST" action="{{ route('academy.assessments.attempt.grade', [$course, $assessment, $attempt]) }}">
        @csrf
        @foreach($questions as $q)
            @php $given = $answers[$q->id] ?? null; $mark = $marks[$q->id] ?? null; @endphp
            <x-cb.card>
                <div class="d-flex justify-content-between gap-3">
                    <div class="flex-grow-1">
                        <div class="fw-semibold mb-2">{{ $loop->iteration }}. {!! nl2br(e($q->question)) !!}</div>
                        @if($q->isChoice())
                            @foreach($q->options as $i => $o)
                                @php $picked = in_array($i, array_map('intval', (array) $given)); $right = in_array($i, $q->correct ?? []); @endphp
                                <div class="{{ $right ? 'text-success' : ($picked ? 'text-danger' : '') }}"><i class="{{ $picked ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line' }}"></i> {{ $o }}@if($right) <small>(correct)</small>@endif</div>
                            @endforeach
                        @else
                            <div class="p-2 bg-light rounded" style="white-space: pre-wrap">{{ $given ?? '(no answer)' }}</div>
                            @if($q->type === 'short_answer')<div class="small text-muted mt-1">Accepted: {{ implode(' · ', $q->accepted_answers ?? []) }}</div>@endif
                        @endif
                    </div>
                    <div style="width: 140px">
                        <label class="form-label small">Mark / {{ $q->points + 0 }}</label>
                        <input name="marks[{{ $q->id }}]" type="number" step="0.5" min="0" max="{{ $q->points }}" class="form-control form-control-sm {{ $q->isManual() && $mark === null ? 'border-warning' : '' }}" value="{{ $mark !== null ? $mark + 0 : ($q->isManual() && $given === null ? 0 : '') }}" @required($q->isManual())>
                    </div>
                </div>
            </x-cb.card>
        @endforeach
        @if($attempt->isSubmitted())<button class="btn btn-primary">Save marks</button>@endif
    </form>

</div></div></div>
@endsection
