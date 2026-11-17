@extends('layouts.master')

@section('content')
@php $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%'; $quiz = $assessment->isQuiz(); $manual = $assessment->isManual(); @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'Results: ' . $assessment->title" :icon="$assessment->icon" :subtitle="$course->title" :back="route('academy.assessments.edit', [$course, $assessment])" :back-label="$assessment->type_label">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $assessment->type_label }}</span>
            <span class="cb-meta-pill">Pass mark {{ $assessment->pass_mark }}%</span>
            <span class="cb-meta-pill">Weight {{ $assessment->weight + 0 }}</span>
            @if($assessment->due_at)<span class="cb-meta-pill">{{ $quiz ? 'Closes' : 'Due' }} {{ $assessment->due_at->format('j M Y, g:ia') }}</span>@endif
        </x-slot:pills>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Students" icon="ri-team-line" :count="$enrollments->count()" :flush="true">
        @if($enrollments->isEmpty())
            <div class="empty-state"><i class="ri-team-line"></i><h6>No students with course access yet</h6></div>
        @elseif($manual)
            <form method="POST" action="{{ route('academy.assessments.scores', [$course, $assessment]) }}">
                @csrf
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Student</th><th>Cohort</th><th style="width:140px">Score / {{ $assessment->max_score + 0 }}</th><th>Feedback <span class="text-muted fw-normal">(optional)</span></th><th class="num">%</th></tr></thead>
                    <tbody>
                    @foreach($enrollments as $e)
                        @php $s = $submissions->get($e->id); @endphp
                        <tr>
                            <td data-label="Student" class="fw-semibold">{{ $e->student->user->name }}<div class="small text-muted">{{ $e->student->student_no }}</div></td>
                            <td data-label="Cohort">{{ $e->cohort->name }}</td>
                            <td data-label="Score"><input name="scores[{{ $e->id }}][score]" type="number" step="0.5" min="0" max="{{ $assessment->max_score }}" class="form-control form-control-sm" value="{{ $s?->score !== null ? $s->score + 0 : '' }}" aria-label="Score for {{ $e->student->user->name }}"></td>
                            <td data-label="Feedback"><input name="scores[{{ $e->id }}][feedback]" maxlength="2000" class="form-control form-control-sm" value="{{ $s?->feedback }}"></td>
                            <td data-label="%" class="num">@if($s && $s->percent !== null)<span class="{{ $s->percent >= $assessment->pass_mark ? 'text-success' : 'text-danger' }}">{{ $pct($s->percent) }}</span>@else — @endif</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                <div class="p-3 d-flex align-items-center gap-2"><button class="btn btn-primary">Save scores</button><span class="form-text">Blank scores are left as they are. Students are told when their score is saved.</span></div>
            </form>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Student</th><th>Cohort</th>@if($quiz)<th>Attempts</th><th>Best</th>@else<th>Handed in</th><th>Grade</th>@endif<th></th></tr></thead>
                <tbody>
                @foreach($enrollments as $e)
                    @php $name = $e->student->user->name; @endphp
                    @if($quiz)
                        @php $mine = $attempts->get($e->id, collect()); $best = $mine->where('needs_review', false)->sortByDesc('percent')->first(); $review = $mine->firstWhere('needs_review', true); @endphp
                        <tr>
                            <td data-label="Student" class="fw-semibold">{{ $name }}</td>
                            <td data-label="Cohort">{{ $e->cohort->name }}</td>
                            <td data-label="Attempts">{{ $mine->count() ?: '—' }}</td>
                            <td data-label="Best">@if($best)<span class="{{ $best->percent >= $assessment->pass_mark ? 'text-success' : 'text-danger' }}">{{ $pct($best->percent) }}</span>@else — @endif</td>
                            <td class="text-end">
                                @if($review)<a href="{{ route('academy.assessments.attempt', [$course, $assessment, $review]) }}" class="btn btn-sm btn-warning">Mark essays</a>
                                @elseif($mine->isNotEmpty())<a href="{{ route('academy.assessments.attempt', [$course, $assessment, $best ?? $mine->first()]) }}" class="btn btn-sm btn-light">View</a>@endif
                            </td>
                        </tr>
                    @else
                        @php $s = $submissions->get($e->id); @endphp
                        <tr>
                            <td data-label="Student" class="fw-semibold">{{ $name }}</td>
                            <td data-label="Cohort">{{ $e->cohort->name }}</td>
                            <td data-label="Handed in">@if($s){{ $s->submitted_at?->format('j M Y, g:ia') }}@if($s->isLate()) <span class="badge bg-warning-subtle text-warning">Late</span>@endif<div class="small text-muted">{{ $s->status_label }}</div>@else <span class="text-muted">Not yet</span>@endif</td>
                            <td data-label="Grade">{{ $s && $s->status === 'graded' ? ($s->score + 0) . ' / ' . ($assessment->max_score + 0) . ' (' . $pct($s->percent) . ')' : '—' }}@if($s && $s->comments->count())<div class="small text-muted"><i class="ri-chat-3-line"></i> {{ $s->comments->count() }}</div>@endif</td>
                            <td class="text-end">@if($s)<button class="btn btn-sm {{ $s->status === 'submitted' ? 'btn-primary' : 'btn-light' }}" data-bs-toggle="collapse" data-bs-target="#sub-{{ $s->id }}">{{ $s->status === 'submitted' ? 'Grade' : 'Open' }}</button>@endif</td>
                        </tr>
                        @if($s)
                            <tr @class(['collapse', 'show' => (int) session('open_sub') === $s->id]) id="sub-{{ $s->id }}"><td colspan="5" class="bg-light">
                                <div class="row g-3">
                                    <div class="col-xl-7">
                                        @if($s->body)<div class="p-3 bg-white border rounded mb-2" style="white-space: pre-wrap">{{ $s->body }}</div>@endif
                                        @if($s->code !== null)
                                            <div class="mb-2"><x-code-editor :value="$s->code" :language="$s->code_language ?? $assessment->code_language ?? 'plain'" :readonly="true" height="120px" :line-target="'#line-' . $s->id" :commented="$s->comments->pluck('line')->filter()->all()" /></div>
                                        @endif
                                        @if($s->file_path)<p><a href="{{ route('academy.assessments.submission.file', [$course, $assessment, $s]) }}"><i class="ri-download-line"></i> {{ $s->file_name }}</a></p>@endif
                                        <form method="POST" action="{{ route('academy.assessments.submission.grade', [$course, $assessment, $s]) }}" class="row g-2 align-items-end">
                                            @csrf
                                            <div class="col-sm-3"><label class="form-label small">Score / {{ $assessment->max_score + 0 }}</label><input name="score" type="number" step="0.5" min="0" max="{{ $assessment->max_score }}" class="form-control form-control-sm" value="{{ $s->score !== null ? $s->score + 0 : '' }}"></div>
                                            <div class="col-sm-9"><label class="form-label small">Overall feedback for the student</label><textarea name="feedback" class="form-control form-control-sm" rows="2">{{ $s->feedback }}</textarea></div>
                                            <div class="col-12 d-flex gap-1"><button name="action" value="grade" class="btn btn-sm btn-success">Save grade</button><button name="action" value="return" class="btn btn-sm btn-soft-warning">Return for changes</button></div>
                                        </form>
                                    </div>
                                    <div class="col-xl-5">
                                        <div class="p-3 bg-white border rounded">
                                            @include('modules.academy.partials.comments', [
                                                'comments' => $s->comments, 'studentId' => $e->student->user_id,
                                                'action' => route('academy.assessments.submission.comment', [$course, $assessment, $s]),
                                                'lineInputId' => $s->code !== null ? 'line-' . $s->id : null,
                                            ])
                                        </div>
                                    </div>
                                </div>
                            </td></tr>
                        @endif
                    @endif
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
