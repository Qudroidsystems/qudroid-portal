@extends('layouts.master')

@section('content')
@php $editing = $assessment->exists; $quiz = $assessment->type === 'quiz'; $taken = $taken ?? false; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$editing ? $assessment->title : 'New ' . strtolower($assessment->type_label)" :icon="$assessment->icon" :subtitle="$course->title" :back="route('academy.content.index', $course)" back-label="Content">
        @if($editing)
            <x-slot:pills>
                <span class="cb-meta-pill">{{ $assessment->type_label }}</span>
                <span class="cb-meta-pill">{{ $assessment->is_published ? 'Published' : 'Draft' }}</span>
                @if($quiz)<span class="cb-meta-pill">{{ $assessment->questions->count() }} questions · {{ rtrim(rtrim(number_format($assessment->questions->sum('points'), 2), '0'), '.') }} points</span>@endif
            </x-slot:pills>
            <x-slot:actions><a href="{{ route('academy.assessments.results', [$course, $assessment]) }}" class="cb-hero-btn"><i class="ri-bar-chart-box-line"></i>Results</a></x-slot:actions>
        @endif
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-{{ $editing && $quiz ? '5' : '8' }}">
            <form method="POST" action="{{ $editing ? route('academy.assessments.update', [$course, $assessment]) : route('academy.assessments.store', $course) }}">
                @csrf @if($editing) @method('PUT') @else <input type="hidden" name="type" value="{{ $assessment->type }}"> @endif
                <x-cb.card title="Details" icon="ri-settings-3-line">
                    <div class="mb-3"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="200" value="{{ old('title', $assessment->title) }}" placeholder="{{ $quiz ? 'e.g. Week 1 quiz: HTML basics' : 'e.g. Build a personal web page' }}"></div>
                    <div class="mb-3"><label class="form-label">Instructions</label><textarea name="instructions" class="form-control" rows="{{ $quiz ? 3 : 6 }}" placeholder="Markdown works here">{{ old('instructions', $assessment->instructions) }}</textarea></div>
                    <div class="row g-3">
                        <div class="col-sm-6"><label class="form-label">Weight in the final grade</label><input name="weight" type="number" step="0.5" min="0" max="100" class="form-control" required value="{{ old('weight', $assessment->weight + 0) }}"><div class="form-text">e.g. 20 for 20%. 0 = practice, doesn't count (unless nothing has a weight).</div></div>
                        <div class="col-sm-6"><label class="form-label">Pass mark (%)</label><input name="pass_mark" type="number" min="0" max="100" class="form-control" required value="{{ old('pass_mark', $assessment->pass_mark) }}"></div>
                        @if($quiz)
                            <div class="col-sm-6"><label class="form-label">Attempts allowed</label><input name="max_attempts" type="number" min="0" max="100" class="form-control" value="{{ old('max_attempts', $assessment->max_attempts) }}"><div class="form-text">0 = unlimited. The best attempt counts.</div></div>
                            <div class="col-sm-6"><label class="form-label">Time limit (minutes)</label><input name="time_limit_minutes" type="number" min="1" max="600" class="form-control" value="{{ old('time_limit_minutes', $assessment->time_limit_minutes) }}" placeholder="No limit"></div>
                        @else
                            <div class="col-sm-6"><label class="form-label">Marked out of</label><input name="max_score" type="number" step="0.5" min="1" max="1000" class="form-control" value="{{ old('max_score', $assessment->max_score + 0) }}"></div>
                        @endif
                        <div class="col-sm-6"><label class="form-label">{{ $quiz ? 'Closes' : 'Due' }} <span class="text-muted">(optional)</span></label><input name="due_at" type="datetime-local" class="form-control" value="{{ old('due_at', $assessment->due_at?->format('Y-m-d\TH:i')) }}">@unless($quiz)<div class="form-text">Late work is still accepted, and marked late.</div>@endunless</div>
                    </div>
                    @if($quiz)
                        <div class="form-check form-switch mt-3"><input class="form-check-input" type="checkbox" name="shuffle" value="1" id="shuffle" @checked(old('shuffle', $assessment->shuffle))><label class="form-check-label" for="shuffle">Shuffle the questions for each attempt</label></div>
                        <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="show_answers" value="1" id="show" @checked(old('show_answers', $assessment->show_answers))><label class="form-check-label" for="show">Show correct answers after submitting</label></div>
                    @endif
                    <div class="form-check form-switch mt-2"><input class="form-check-input" type="checkbox" name="is_published" value="1" id="pub" @checked(old('is_published', $assessment->is_published)) @disabled($quiz && !$editing)><label class="form-check-label" for="pub">Published (students on the course can see it)</label>
                        @if($quiz && !$editing)<div class="form-text">Add questions first, then publish.</div>@endif</div>
                    <div class="d-flex gap-2 mt-3">
                        <button class="btn btn-primary">{{ $editing ? 'Save' : 'Create' }}</button>
                        @if($editing && !$taken)<button form="del-assessment" class="btn btn-soft-danger ms-auto"><i class="ri-delete-bin-line"></i> Delete</button>@endif
                    </div>
                </x-cb.card>
            </form>
            @if($editing)<form id="del-assessment" method="POST" action="{{ route('academy.assessments.destroy', [$course, $assessment]) }}" onsubmit="return confirm('Delete this {{ strtolower($assessment->type_label) }}?')">@csrf @method('DELETE')</form>@endif
        </div>

        @if($editing && $quiz)
            <div class="col-xl-7">
                <x-cb.card title="Questions" icon="ri-questionnaire-line" :count="$assessment->questions->count()" :flush="true">
                    @if($taken)<div class="cb-banner info m-3"><i class="ri-information-line"></i><div>Students have attempted this quiz. You can fix wording and answers; marks already given are kept.</div></div>@endif
                    @forelse($assessment->questions as $q)
                        <div class="p-3 border-bottom">
                            <div class="d-flex justify-content-between gap-2">
                                <div><span class="fw-semibold">{{ $loop->iteration }}.</span> {!! nl2br(e($q->question)) !!}
                                    <div class="small text-muted mt-1">{{ \App\Models\Academy\Question::TYPES[$q->type] }} · {{ $q->points + 0 }} {{ $q->points == 1 ? 'point' : 'points' }}</div>
                                    @if($q->isChoice())<ul class="small mb-0 mt-1 ps-3">@foreach($q->options as $i => $o)<li class="{{ in_array($i, $q->correct ?? []) ? 'text-success fw-semibold' : '' }}">{{ $o }}@if(in_array($i, $q->correct ?? [])) <i class="ri-check-line"></i>@endif</li>@endforeach</ul>@endif
                                    @if($q->type === 'short_answer')<div class="small mt-1">Accepted: <span class="text-success">{{ implode(' · ', $q->accepted_answers ?? []) }}</span></div>@endif
                                </div>
                                <div class="d-flex gap-1 align-items-start">
                                    <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#q-edit-{{ $q->id }}"><i class="ri-edit-line"></i></button>
                                    @unless($taken)<form method="POST" action="{{ route('academy.assessments.questions.destroy', [$course, $assessment, $q]) }}" onsubmit="return confirm('Remove this question?')">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-line"></i></button></form>@endunless
                                </div>
                            </div>
                            <div class="collapse mt-3" id="q-edit-{{ $q->id }}">
                                @include('modules.academy.assessments._question-form', ['question' => $q, 'action' => route('academy.assessments.questions.update', [$course, $assessment, $q]), 'method' => 'PUT', 'key' => 'e' . $q->id])
                            </div>
                        </div>
                    @empty
                        <div class="empty-state"><i class="ri-questionnaire-line"></i><h6>No questions yet</h6><p>Add the first question below.</p></div>
                    @endforelse
                </x-cb.card>
                <x-cb.card title="Add a question" icon="ri-add-circle-line">
                    @include('modules.academy.assessments._question-form', ['question' => new \App\Models\Academy\Question(['type' => old('type', 'single'), 'points' => 1]), 'action' => route('academy.assessments.questions.store', [$course, $assessment]), 'method' => 'POST', 'key' => 'new'])
                </x-cb.card>
            </div>
        @endif
    </div>

</div></div></div>
@endsection

@push('scripts')
<script>
// Show only the answer fields that fit the chosen question type.
document.querySelectorAll('[data-question-form]').forEach(function (form) {
    var type = form.querySelector('[name=type]');
    function sync() {
        var t = type.value;
        form.querySelectorAll('[data-for]').forEach(function (el) {
            var on = el.dataset.for.split(' ').indexOf(t) >= 0;
            el.style.display = on ? '' : 'none';
            el.querySelectorAll('input, textarea').forEach(function (i) { i.disabled = !on; });
        });
        form.querySelectorAll('[data-correct]').forEach(function (el) { el.type = t === 'multiple' ? 'checkbox' : 'radio'; });
    }
    type.addEventListener('change', sync);
    sync();
});
</script>
@endpush
