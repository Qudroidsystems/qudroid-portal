@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$p->name()" icon="ri-user-star-line" :subtitle="($p->student->student_no ? $p->student->student_no . ' · ' : '') . $p->status_label" :back="route('website.students.index')" back-label="Students on the website">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('site.students.show', $p) }}" target="_blank"><i class="ri-external-link-line me-1"></i>{{ $p->isPublic() ? 'View' : 'Page (not public)' }}</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('website.students.update', $p) }}" enctype="multipart/form-data" id="profileForm">
                @csrf @method('PUT')
                <x-cb.card title="Profile" icon="ri-user-3-line">@include('modules.website.students._fields', ['p' => $p])</x-cb.card>
            </form>
            @include('modules.website.students._projects', [
                'p' => $p, 'project' => $project, 'courses' => $courses,
                'routes' => [
                    'store' => fn () => route('website.students.projects.store', $p),
                    'update' => fn ($pr) => route('website.students.projects.update', [$p, $pr]),
                    'destroy' => fn ($pr) => route('website.students.projects.destroy', [$p, $pr]),
                ],
                'editUrl' => fn ($pr) => $pr ? route('website.students.edit', [$p, 'project' => $pr->id]) . '#projectForm' : route('website.students.edit', $p),
            ])
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Publishing" icon="ri-eye-line">
                <div class="form-check form-switch mb-1">
                    <input class="form-check-input" type="checkbox" name="consent" value="1" id="consent" form="profileForm" @checked($p->consented_at)>
                    <label class="form-check-label" for="consent">The student agrees to be on the website</label>
                </div>
                <div class="form-text mb-3">@if($p->consented_at)Agreed {{ $p->consented_at->format('j M Y') }}. @endif Tick only if they agreed (for example on a signed form). Un-ticking takes the page down.</div>
                <label class="form-label small">Status</label>
                <select name="status" class="form-select mb-2" form="profileForm">@foreach(\App\Models\Website\StudentProfile::STATUSES as $k => $label)<option value="{{ $k }}" @selected(old('status', $p->status) === $k)>{{ $label }}</option>@endforeach</select>
                <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="featured" value="1" id="feat" form="profileForm" @checked(old('featured', $p->featured))><label class="form-check-label" for="feat">Featured (first on the page and in Students blocks)</label></div>
                <label class="form-label small">Order</label><input name="position" type="number" min="0" max="999" class="form-control mb-3" form="profileForm" value="{{ old('position', $p->position ?? 0) }}">
                <button class="btn btn-primary w-100" form="profileForm">Save</button>
                @if($p->reviewed_at)<div class="small text-muted mt-2">Last reviewed {{ $p->reviewed_at->diffForHumans() }}.</div>@endif
                <div class="text-end mt-1"><form method="POST" action="{{ route('website.students.destroy', $p) }}" onsubmit="return confirm('Delete this profile and its projects? The student can start again.')">@csrf @method('DELETE')<button class="btn btn-link text-danger btn-sm">Delete profile</button></form></div>
            </x-cb.card>
            <x-cb.card title="Studied with us" icon="ri-graduation-cap-line" class="mt-3">
                @forelse($studies as $st)
                    <div class="small border-top py-2"><strong>{{ $st['course']->title }}</strong><div class="text-muted">{{ collect([$st['cohort'], $st['completed'] ? 'Completed' : 'Studying'])->filter()->implode(' · ') }}</div></div>
                @empty
                    <div class="small text-muted">No active or completed courses.</div>
                @endforelse
                <div class="form-text">Shown on the page automatically from their enrolments.</div>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@include('modules.website.students._styles')
@endsection
