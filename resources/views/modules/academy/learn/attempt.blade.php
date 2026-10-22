@extends('layouts.master')

@section('content')
@php
    $done = $attempt->isSubmitted();
    $answers = $attempt->answers ?? [];
    $marks = $attempt->marks ?? [];
    $reveal = $done && $assessment->show_answers && !$attempt->needs_review;
    $left = $done ? null : $attempt->secondsLeft();
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$assessment->title" icon="ri-questionnaire-line" :subtitle="'Attempt ' . $attempt->attempt_no . ' · ' . $course->title" :back="route('academy.learn.assessment', [$course, $assessment])" back-label="Quiz">
        <x-slot:pills>
            @if($done)
                <span class="cb-meta-pill">{{ $attempt->needs_review ? 'Being marked' : 'Score ' . rtrim(rtrim(number_format((float) $attempt->percent, 1), '0'), '.') . '%' }}</span>
                @if(!$attempt->needs_review)<span class="cb-meta-pill">{{ $attempt->percent >= $assessment->pass_mark ? 'Passed' : 'Not passed' }} (pass mark {{ $assessment->pass_mark }}%)</span>@endif
            @elseif($left !== null)
                <span class="cb-meta-pill"><i class="ri-timer-line me-1"></i><span id="timer" data-seconds="{{ $left }}">{{ gmdate($left >= 3600 ? 'G:i:s' : 'i:s', $left) }}</span> left</span>
            @endif
        </x-slot:pills>
    </x-cb.hero>

    @include('partials.flash')

    @if($done && $attempt->needs_review)
        <div class="cb-banner info"><i class="ri-time-line"></i><div>Your essay answers are with your instructor. You'll be told when they are marked.</div></div>
    @endif

    <form method="POST" action="{{ route('academy.learn.submit', [$course, $attempt]) }}" id="quiz-form">
        @csrf
        @foreach($questions as $q)
            @php $given = $answers[$q->id] ?? null; $mark = $marks[$q->id] ?? null; @endphp
            <x-cb.card>
                <div class="d-flex justify-content-between gap-2 mb-2">
                    <div class="fw-semibold">{{ $loop->iteration }}. {!! nl2br(e($q->question)) !!}</div>
                    <span class="text-muted small text-nowrap">@if($done && $mark !== null){{ $mark + 0 }} / @endif{{ $q->points + 0 }} {{ $q->points == 1 ? 'pt' : 'pts' }}</span>
                </div>
                @if($q->isChoice())
                    @foreach($q->options as $i => $o)
                        @php $picked = in_array($i, array_map('intval', (array) $given)); $right = in_array($i, $q->correct ?? []); @endphp
                        <div class="form-check {{ $reveal && $right ? 'text-success fw-semibold' : ($reveal && $picked ? 'text-danger' : '') }}">
                            <input class="form-check-input" type="{{ $q->type === 'multiple' ? 'checkbox' : 'radio' }}" name="answers[{{ $q->id }}]{{ $q->type === 'multiple' ? '[]' : '' }}" value="{{ $i }}" id="q{{ $q->id }}o{{ $i }}" @checked($picked) @disabled($done)>
                            <label class="form-check-label" for="q{{ $q->id }}o{{ $i }}">{{ $o }}</label>
                        </div>
                    @endforeach
                    @if($q->type === 'multiple' && !$done)<div class="form-text">Tick all that apply.</div>@endif
                @elseif($q->type === 'short_answer')
                    <input name="answers[{{ $q->id }}]" class="form-control" maxlength="500" value="{{ $given }}" @disabled($done) autocomplete="off">
                    @if($reveal && (float) $mark < (float) $q->points)<div class="small text-success mt-1">Accepted: {{ implode(' · ', $q->accepted_answers ?? []) }}</div>@endif
                @else
                    <textarea name="answers[{{ $q->id }}]" class="form-control" rows="6" maxlength="10000" @disabled($done)>{{ $given }}</textarea>
                @endif
                @if($reveal && $q->explanation)<div class="small text-muted mt-2"><i class="ri-lightbulb-line"></i> {{ $q->explanation }}</div>@endif
            </x-cb.card>
        @endforeach

        @unless($done)
            <button class="btn btn-primary btn-lg" onclick="return confirm('Submit your answers? You can\'t change them afterwards.')"><i class="ri-send-plane-line"></i> Submit</button>
        @endunless
    </form>

</div></div></div>
@endsection

@if(!$done && $left !== null)
@push('scripts')
<script>
// Countdown; hands the answers in automatically when time is up.
(function () {
    var el = document.getElementById('timer'), left = parseInt(el.dataset.seconds, 10), form = document.getElementById('quiz-form');
    function show() { var m = Math.floor(left / 60), s = left % 60; el.textContent = m + ':' + (s < 10 ? '0' : '') + s; }
    var t = setInterval(function () {
        left--; show();
        if (left <= 0) { clearInterval(t); form.submit(); }
    }, 1000);
})();
</script>
@endpush
@endif
