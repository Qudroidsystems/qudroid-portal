@extends('layouts.master')

@section('content')
@php
    $pct = fn ($v) => rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%';
    $quiz = $assessment->isQuiz();
    $working = $enrollment && $enrollment->status === 'active';
    $open = $attempts->firstWhere('submitted_at', null);
    $used = $attempts->count();
    $left = $assessment->max_attempts > 0 ? max(0, $assessment->max_attempts - $used) : null;
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$assessment->title" :icon="$assessment->icon" :subtitle="$course->title" :back="route('academy.learn.course', $course)" back-label="Course">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $assessment->type_label }}</span>
            @if($quiz)<span class="cb-meta-pill">{{ $questionCount }} questions</span>@endif
            @if($assessment->time_limit_minutes)<span class="cb-meta-pill"><i class="ri-timer-line me-1"></i>{{ $assessment->time_limit_minutes }} minutes</span>@endif
            <span class="cb-meta-pill">Pass mark {{ $assessment->pass_mark }}%</span>
            @if($assessment->due_at)<span class="cb-meta-pill"><i class="ri-calendar-line me-1"></i>{{ $quiz ? 'Closes' : 'Due' }} {{ $assessment->due_at->format('j M Y, g:ia') }}</span>@endif
        </x-slot:pills>
        @if($teacher)
            <x-slot:actions><a href="{{ route('academy.assessments.edit', [$course, $assessment]) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a><a href="{{ route('academy.assessments.results', [$course, $assessment]) }}" class="cb-hero-btn"><i class="ri-bar-chart-box-line"></i>Results</a></x-slot:actions>
        @endif
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            @if($assessment->instructions)<x-cb.card title="Instructions" icon="ri-information-line"><div class="lesson-body">{!! $assessment->instructionsHtml() !!}</div></x-cb.card>@endif

            @if($quiz)
                <x-cb.card>
                    @if(!$enrollment)
                        <p class="text-muted mb-0">You are viewing this as a teacher. Students start the quiz here.</p>
                    @elseif($open)
                        <a href="{{ route('academy.learn.attempt', [$course, $open]) }}" class="btn btn-primary"><i class="ri-play-line"></i> Continue attempt {{ $open->attempt_no }}</a>
                    @elseif(!$working)
                        <p class="text-muted mb-0">This course is finished, so the quiz is closed.</p>
                    @elseif($assessment->isPastDue())
                        <p class="text-muted mb-0">This quiz closed on {{ $assessment->due_at->format('j M Y, g:ia') }}.</p>
                    @elseif($left === 0)
                        <p class="text-muted mb-0">You have used all {{ $assessment->max_attempts }} {{ \Illuminate\Support\Str::plural('attempt', $assessment->max_attempts) }}.</p>
                    @else
                        <form method="POST" action="{{ route('academy.learn.start', [$course, $assessment]) }}">@csrf
                            <button class="btn btn-primary btn-lg"><i class="ri-play-line"></i> {{ $used ? 'Try again' : 'Start the quiz' }}</button>
                            <span class="text-muted ms-2">@if($left !== null){{ $left }} {{ \Illuminate\Support\Str::plural('attempt', $left) }} left @else Unlimited attempts @endif @if($assessment->time_limit_minutes)· the timer starts when you begin @endif</span>
                        </form>
                    @endif
                </x-cb.card>
            @else
                @if($submission)
                    <x-cb.card title="Your work" icon="ri-file-text-line">
                        <p class="mb-2">@include('modules.academy.partials.pill', ['status' => $submission->status === 'graded' ? 'completed' : ($submission->status === 'returned' ? 'pending' : 'admitted'), 'label' => $submission->status_label])
                            <span class="text-muted small ms-1">Handed in {{ $submission->submitted_at?->format('j M Y, g:ia') }}@if($submission->isLate()) · late @endif</span></p>
                        @if($submission->status === 'graded')<p class="fs-5 mb-2">Grade: <strong>{{ $submission->score + 0 }} / {{ $assessment->max_score + 0 }}</strong> ({{ $pct($submission->percent) }})</p>@endif
                        @if($submission->feedback)<div class="cb-banner info"><i class="ri-chat-quote-line"></i><div><strong>Feedback:</strong> {!! nl2br(e($submission->feedback)) !!}</div></div>@endif
                        @if($submission->body)<div class="p-3 bg-light rounded mb-2" style="white-space: pre-wrap">{{ $submission->body }}</div>@endif
                        @if($submission->file_path)<a href="{{ route('academy.learn.my-file', [$course, $assessment]) }}"><i class="ri-attachment-2"></i> {{ $submission->file_name }}</a>@endif
                    </x-cb.card>
                @endif
                @if($enrollment && $working && (!$submission || $submission->status !== 'graded'))
                    <x-cb.card :title="$submission ? 'Hand in again' : 'Hand in your work'" icon="ri-upload-2-line">
                        <form method="POST" action="{{ route('academy.learn.hand-in', [$course, $assessment]) }}" enctype="multipart/form-data">@csrf
                            <textarea name="body" class="form-control mb-2" rows="6" placeholder="Your answer, or a link to your work (e.g. GitHub)">{{ old('body', $submission?->body) }}</textarea>
                            <input type="file" name="file" class="form-control mb-2">
                            <div class="form-text mb-2">@if($submission?->file_path)A new file replaces {{ $submission->file_name }}. @endif You can hand in again until it is graded.</div>
                            <button class="btn btn-primary"><i class="ri-send-plane-line"></i> Hand in</button>
                        </form>
                    </x-cb.card>
                @elseif(!$enrollment)
                    <x-cb.card><p class="text-muted mb-0">You are viewing this as a teacher. Students hand in their work here.</p></x-cb.card>
                @endif
            @endif
        </div>

        @if($quiz && $attempts->whereNotNull('submitted_at')->isNotEmpty())
            <div class="col-xl-4">
                <x-cb.card title="Your attempts" icon="ri-history-line" :flush="true">
                    <div class="list-group list-group-flush">
                        @foreach($attempts->whereNotNull('submitted_at') as $a)
                            <a href="{{ route('academy.learn.attempt', [$course, $a]) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                                <span>Attempt {{ $a->attempt_no }}<span class="d-block small text-muted">{{ $a->submitted_at->format('j M Y, g:ia') }}</span></span>
                                @if($a->needs_review)<span class="text-muted small">Being marked</span>@else<strong class="{{ $a->percent >= $assessment->pass_mark ? 'text-success' : 'text-danger' }}">{{ $pct($a->percent) }}</strong>@endif
                            </a>
                        @endforeach
                    </div>
                </x-cb.card>
            </div>
        @endif
    </div>

</div></div></div>
@endsection
