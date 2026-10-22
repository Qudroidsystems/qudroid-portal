@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$lesson->title" :icon="$lesson->icon" :subtitle="$course->title" :back="$learning ? route('academy.my') : route('academy.courses.show', $course)" :back-label="$learning ? 'My academy' : 'Course'">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $lesson->type_label }}</span>
            @if($lesson->duration_minutes)<span class="cb-meta-pill"><i class="ri-time-line me-1"></i>{{ $lesson->duration_minutes }} min</span>@endif
            @if($learning)<span class="cb-meta-pill"><i class="ri-progress-3-line me-1"></i>{{ $enrollment->progress_percent }}% complete</span>@endif
            @if($teacher && !$lesson->is_published)<span class="cb-meta-pill"><i class="ri-eye-off-line me-1"></i>Draft — students can't see this</span>@endif
            @if(!$learning && $lesson->is_preview && !$teacher)<span class="cb-meta-pill">Free preview</span>@endif
        </x-slot:pills>
        @if($teacher && auth()->user()->canany(['Manage academy', 'Manage course content']))
            <x-slot:actions><a href="{{ route('academy.content.lessons.edit', [$course, $lesson]) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit lesson</a></x-slot:actions>
        @endif
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8 order-xl-1">
            <x-cb.card>
                @if($lesson->type === 'video')
                    @if($lesson->embedUrl())
                        <div class="ratio ratio-16x9 mb-3 rounded overflow-hidden"><iframe src="{{ $lesson->embedUrl() }}" title="{{ $lesson->title }}" allow="accelerometer; encrypted-media; gyroscope; picture-in-picture; fullscreen" allowfullscreen loading="lazy" referrerpolicy="strict-origin-when-cross-origin"></iframe></div>
                    @else
                        <p><a href="{{ $lesson->video_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary"><i class="ri-play-circle-line me-1"></i>Watch the video</a></p>
                    @endif
                @elseif($lesson->type === 'file' && $lesson->file_path)
                    <div class="d-flex align-items-center gap-3 p-3 mb-3 border rounded">
                        <i class="ri-file-download-line fs-1 text-primary"></i>
                        <div class="flex-grow-1"><div class="fw-semibold">{{ $lesson->file_name }}</div><div class="small text-muted">{{ number_format(($lesson->file_size ?? 0) / 1048576, 1) }} MB</div></div>
                        <a href="{{ route('academy.learn.download', [$course, $lesson]) }}" class="btn btn-primary">Download</a>
                    </div>
                @elseif($lesson->type === 'link')
                    <p><a href="{{ $lesson->external_url }}" target="_blank" rel="noopener noreferrer" class="btn btn-primary"><i class="ri-external-link-line me-1"></i>Open the link</a> <span class="small text-muted ms-2">{{ parse_url($lesson->external_url, PHP_URL_HOST) }}</span></p>
                @endif

                @if($lesson->body)
                    <div class="lesson-body">{!! $lesson->bodyHtml() !!}</div>
                @elseif($lesson->type === 'text')
                    <p class="text-muted mb-0">This lesson has no text yet.</p>
                @endif
            </x-cb.card>

            <div class="d-flex justify-content-between align-items-center gap-2 flex-wrap mb-3">
                <div>@if($prev)<a href="{{ route('academy.learn.lesson', [$course, $prev]) }}" class="btn btn-light"><i class="ri-arrow-left-line"></i> {{ \Illuminate\Support\Str::limit($prev->title, 40) }}</a>@endif</div>
                <div class="d-flex gap-2">
                    @if($learning && $enrollment->status === 'active' && $lesson->is_published)
                        @if($doneIds->contains($lesson->id))
                            <form method="POST" action="{{ route('academy.learn.complete', [$course, $lesson]) }}">@csrf<input type="hidden" name="done" value="0"><button class="btn btn-soft-success"><i class="ri-checkbox-circle-fill"></i> Completed · undo</button></form>
                        @else
                            <form method="POST" action="{{ route('academy.learn.complete', [$course, $lesson]) }}">@csrf<input type="hidden" name="done" value="1">@if($next)<input type="hidden" name="next" value="1"><input type="hidden" name="next_id" value="{{ $next->id }}">@endif<button class="btn btn-success"><i class="ri-check-line"></i> Mark complete{{ $next ? ' & continue' : '' }}</button></form>
                        @endif
                    @endif
                    @if($next)<a href="{{ route('academy.learn.lesson', [$course, $next]) }}" class="btn btn-light">{{ \Illuminate\Support\Str::limit($next->title, 40) }} <i class="ri-arrow-right-line"></i></a>@endif
                </div>
            </div>
        </div>

        <div class="col-xl-4 order-xl-2">
            <x-cb.card title="Course outline" icon="ri-list-ordered" :flush="true">
                @if($learning)
                    <div class="px-3 pt-3"><div class="progress" style="height:8px" role="progressbar" aria-valuenow="{{ $enrollment->progress_percent }}" aria-valuemin="0" aria-valuemax="100"><div class="progress-bar bg-success" style="width: {{ $enrollment->progress_percent }}%"></div></div></div>
                @endif
                @php $currentSection = false; @endphp
                <div class="list-group list-group-flush mt-2">
                    @foreach($outline as $item)
                        @if($currentSection !== $item->section_id)
                            @php $currentSection = $item->section_id; @endphp
                            <div class="list-group-item bg-light small fw-semibold text-uppercase text-muted">{{ $item->section?->title ?? 'Other lessons' }}</div>
                        @endif
                        @php $canOpen = $openIds->contains($item->id); @endphp
                        @if($canOpen)<a href="{{ route('academy.learn.lesson', [$course, $item]) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center {{ $item->id === $lesson->id ? 'active' : '' }}">@else<div class="list-group-item d-flex gap-2 align-items-center text-muted">@endif
                            @if($doneIds->contains($item->id))<i class="ri-checkbox-circle-fill text-success"></i>@elseif($canOpen)<i class="{{ $item->icon }}"></i>@else<i class="ri-lock-line"></i>@endif
                            <span class="flex-grow-1">{{ $item->title }}@if(!$item->is_published) <span class="badge bg-secondary-subtle text-secondary">Draft</span>@endif</span>
                            @if($item->duration_minutes)<small>{{ $item->duration_minutes }}m</small>@endif
                        @if($canOpen)</a>@else</div>@endif
                    @endforeach
                </div>
            </x-cb.card>
            @if(!$learning && !$teacher)
                <div class="cb-banner info"><i class="ri-lock-unlock-line"></i><div>@if($enrollment)Your course access opens once your payment is confirmed. <a href="{{ route('billing.index') }}">My bills</a>@else Enroll on this course to open every lesson.@endif</div></div>
            @endif
        </div>
    </div>

</div></div></div>
@endsection

@push('styles')
<style>
.lesson-body { font-size: 15px; line-height: 1.7; }
.lesson-body h1, .lesson-body h2, .lesson-body h3 { margin-top: 1.2em; }
.lesson-body pre { background: #0f172a; color: #e2e8f0; padding: 14px 16px; border-radius: 8px; overflow-x: auto; }
.lesson-body code { font-size: 13px; }
.lesson-body img { max-width: 100%; height: auto; }
.lesson-body blockquote { border-left: 4px solid #cbd5e1; padding-left: 12px; color: #475569; }
</style>
@endpush
