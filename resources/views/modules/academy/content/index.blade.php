@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $count = $sections->sum(fn ($s) => $s->lessons->count()) + $loose->count(); @endphp
    <x-cb.hero :title="'Content: ' . $course->title" icon="ri-folder-open-line" subtitle="Sections and lessons. Students see published lessons once their course access is open; free previews are open to anyone who can see the course."
               :back="route('academy.courses.show', $course)" back-label="Course">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $sections->count() }} sections</span>
            <span class="cb-meta-pill">{{ $count }} lessons</span>
            @unless($course->isPublished())<span class="cb-meta-pill"><i class="ri-eye-off-line me-1"></i>Course not published</span>@endunless
        </x-slot:pills>
        <x-slot:actions>
            <a href="{{ route('academy.content.lessons.create', $course) }}" class="cb-hero-btn"><i class="ri-add-line"></i>New lesson</a>
            @if($count)<a href="{{ route('academy.learn.course', $course) }}" class="cb-hero-btn"><i class="ri-eye-line"></i>View as student</a>@endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            @forelse($sections as $section)
                <x-cb.card :title="($loop->iteration) . '. ' . $section->title" icon="ri-folder-2-line" :count="$section->lessons->count()" :flush="true">
                    <x-slot:tools>
                        <a href="{{ route('academy.content.lessons.create', [$course, 'section' => $section->id]) }}" class="btn btn-sm btn-soft-primary"><i class="ri-add-line"></i> Lesson</a>
                        <form method="POST" action="{{ route('academy.content.sections.move', [$course, $section]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-light" title="Move up" @disabled($loop->first)><i class="ri-arrow-up-line"></i></button></form>
                        <form method="POST" action="{{ route('academy.content.sections.move', [$course, $section]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-light" title="Move down" @disabled($loop->last)><i class="ri-arrow-down-line"></i></button></form>
                        <button type="button" class="btn btn-sm btn-light" data-bs-toggle="collapse" data-bs-target="#sec-edit-{{ $section->id }}" title="Rename"><i class="ri-edit-line"></i></button>
                    </x-slot:tools>
                    <div class="collapse border-bottom p-3" id="sec-edit-{{ $section->id }}">
                        <form method="POST" action="{{ route('academy.content.sections.update', [$course, $section]) }}" class="row g-2">
                            @csrf @method('PUT')
                            <div class="col-md-5"><input name="title" class="form-control form-control-sm" value="{{ $section->title }}" required maxlength="200"></div>
                            <div class="col-md-5"><input name="description" class="form-control form-control-sm" value="{{ $section->description }}" placeholder="Description"></div>
                            <div class="col-md-2 d-flex gap-1"><button class="btn btn-sm btn-primary">Save</button><button form="sec-del-{{ $section->id }}" class="btn btn-sm btn-soft-danger" title="Remove section"><i class="ri-delete-bin-line"></i></button></div>
                        </form>
                        <form id="sec-del-{{ $section->id }}" method="POST" action="{{ route('academy.content.sections.destroy', [$course, $section]) }}" onsubmit="return confirm('Remove this section? Its lessons are kept.')">@csrf @method('DELETE')</form>
                    </div>
                    @if($section->description)<div class="px-3 pt-2 small text-muted">{{ $section->description }}</div>@endif
                    @if($section->lessons->isEmpty())
                        <div class="p-3 small text-muted">No lessons in this section yet.</div>
                    @else
                        <div class="list-group list-group-flush">
                            @foreach($section->lessons as $lesson)
                                @include('modules.academy.content._lesson-row', ['first' => $loop->first, 'last' => $loop->last])
                            @endforeach
                        </div>
                    @endif
                </x-cb.card>
            @empty
                @if($loose->isEmpty())
                    <x-cb.card><div class="empty-state"><i class="ri-folder-open-line"></i><h6>No content yet</h6><p>Add a section (e.g. "Week 1: HTML basics"), then add lessons to it.</p></div></x-cb.card>
                @endif
            @endforelse

            @if($loose->isNotEmpty())
                <x-cb.card title="Other lessons" icon="ri-file-list-line" :count="$loose->count()" :flush="true">
                    <div class="list-group list-group-flush">
                        @foreach($loose as $lesson)
                            @include('modules.academy.content._lesson-row', ['first' => $loop->first, 'last' => $loop->last])
                        @endforeach
                    </div>
                </x-cb.card>
            @endif
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Quizzes and assignments" icon="ri-questionnaire-line" :count="$assessments->count()" :flush="true">
                <x-slot:tools>
                    <a href="{{ route('academy.assessments.create', [$course, 'type' => 'quiz']) }}" class="btn btn-sm btn-soft-primary"><i class="ri-add-line"></i> Quiz</a>
                    <a href="{{ route('academy.assessments.create', [$course, 'type' => 'assignment']) }}" class="btn btn-sm btn-soft-primary"><i class="ri-add-line"></i> Assignment</a>
                </x-slot:tools>
                @if($assessments->isEmpty())
                    <div class="p-3 small text-muted">None yet. Their weighted results make up the student's grade; the pass grade ({{ $course->pass_grade }}%) is set on the course.</div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($assessments as $a)
                            <a href="{{ route('academy.assessments.edit', [$course, $a]) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center">
                                <i class="{{ $a->icon }}"></i>
                                <span class="flex-grow-1">{{ $a->title }}<span class="d-block small text-muted">{{ $a->type_label }}@if($a->isQuiz()) · {{ $a->questions_count }} questions @endif · weight {{ $a->weight + 0 }}</span></span>
                                @unless($a->is_published)<span class="badge bg-secondary-subtle text-secondary">Draft</span>@endunless
                            </a>
                        @endforeach
                    </div>
                    @php $w = $assessments->where('is_published', true)->sum(fn ($a) => (float) $a->weight); @endphp
                    @if($w > 0 && abs($w - 100) > 0.01)<div class="px-3 py-2 small text-warning border-top">Published weights add up to {{ $w + 0 }}, not 100. Grades are still worked out in proportion.</div>@endif
                @endif
            </x-cb.card>
            <x-cb.card title="Add a section" icon="ri-folder-add-line">
                <form method="POST" action="{{ route('academy.content.sections.store', $course) }}">
                    @csrf
                    <div class="mb-2"><input name="title" class="form-control" required maxlength="200" placeholder="e.g. Week 1: HTML basics"></div>
                    <div class="mb-2"><textarea name="description" class="form-control" rows="2" placeholder="Description (optional)"></textarea></div>
                    <button class="btn btn-primary w-100">Add section</button>
                </form>
            </x-cb.card>
            <x-cb.card title="Tips" icon="ri-lightbulb-line">
                <ul class="small text-muted mb-0 ps-3">
                    <li>Readings use Markdown: <code>**bold**</code>, <code># Heading</code>, <code>- list</code>, <code>```code```</code>.</li>
                    <li>Videos: paste a YouTube or Vimeo link (unlisted works).</li>
                    <li>Files are private: only people who can open the lesson can download them.</li>
                    <li>Progress counts published lessons only.</li>
                </ul>
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
