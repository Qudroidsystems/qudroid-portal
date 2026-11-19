@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Students on the website" icon="ri-user-star-line" subtitle="Students fill in their own profile in My academy → My public profile. Nothing is public until they agree and you publish it." :back="route('website.index')" back-label="Website">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('site.students') }}" target="_blank"><i class="ri-external-link-line me-1"></i>Our students page</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-9">
            <x-cb.card title="Profiles" icon="ri-user-star-line" :count="$profiles->count()" :flush="true">
                <x-slot:tools>
                    <div class="btn-group btn-group-sm">
                        <a href="{{ route('website.students.index') }}" class="btn btn-{{ $status ? 'light' : 'primary' }}">All</a>
                        @foreach(\App\Models\Website\StudentProfile::STATUSES as $k => $label)
                            <a href="{{ route('website.students.index', ['status' => $k]) }}" class="btn btn-{{ $status === $k ? 'primary' : 'light' }}">{{ $label }} @if($counts[$k] ?? 0)<span class="badge bg-{{ $k === 'pending' ? 'warning' : 'secondary' }}-subtle text-{{ $k === 'pending' ? 'warning' : 'secondary' }} ms-1">{{ $counts[$k] }}</span>@endif</a>
                        @endforeach
                    </div>
                </x-slot:tools>
                @forelse($profiles as $p)
                    <div class="d-flex gap-3 align-items-center border-top p-3 flex-wrap">
                        <div class="sp-photo" style="width:48px;height:48px;font-size:17px">@if($p->photoUrl())<img src="{{ $p->photoUrl() }}" alt="">@else<span>{{ $p->initials() }}</span>@endif</div>
                        <div class="flex-grow-1 min-w-0">
                            <a href="{{ route('website.students.edit', $p) }}" class="fw-semibold">{{ $p->name() }}</a>
                            @if($p->featured)<span class="badge bg-warning-subtle text-warning ms-1"><i class="ri-star-fill"></i> Featured</span>@endif
                            @unless($p->consented_at)<span class="badge bg-light text-muted ms-1" title="The student hasn't agreed to be on the website">Not agreed</span>@endunless
                            <div class="small text-muted text-truncate">{{ $p->headline ?: 'No headline yet' }} · {{ $p->projects_count }} {{ \Illuminate\Support\Str::plural('project', $p->projects_count) }}</div>
                        </div>
                        <span class="badge bg-{{ ['draft' => 'secondary', 'pending' => 'warning', 'published' => 'success', 'hidden' => 'danger'][$p->status] }}-subtle text-{{ ['draft' => 'secondary', 'pending' => 'warning', 'published' => 'success', 'hidden' => 'danger'][$p->status] }}">{{ $p->status_label }}</span>
                        @if($p->status === 'pending' || ($p->status === 'hidden' && $p->consented_at))
                            <form method="POST" action="{{ route('website.students.review', $p) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn btn-sm btn-success"><i class="ri-check-line"></i> Publish</button></form>
                        @elseif($p->status === 'published')
                            <a href="{{ route('site.students.show', $p) }}" target="_blank" class="btn btn-sm btn-ghost-secondary" title="View"><i class="ri-external-link-line"></i></a>
                            <form method="POST" action="{{ route('website.students.review', $p) }}">@csrf<input type="hidden" name="status" value="hidden"><button class="btn btn-sm btn-soft-danger">Hide</button></form>
                        @endif
                        <a href="{{ route('website.students.edit', $p) }}" class="btn btn-sm btn-soft-primary">Edit</a>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">No profiles {{ $status ? 'here' : 'yet' }}. Students start theirs in My academy → My public profile, or add one for a student here.</div>
                @endforelse
            </x-cb.card>
        </div>
        <div class="col-xl-3">
            <x-cb.card title="Add a student" icon="ri-user-add-line">
                <p class="small text-muted">Start a profile for a student and fill it in for them. You will need their agreement before publishing it.</p>
                <form method="POST" action="{{ route('website.students.store') }}">
                    @csrf
                    <select name="student_id" class="form-select mb-2" required>
                        <option value="">Choose a student…</option>
                        @foreach($without as $s)<option value="{{ $s->id }}">{{ $s->user?->name }}{{ $s->student_no ? ' · ' . $s->student_no : '' }}</option>@endforeach
                    </select>
                    <button class="btn btn-soft-primary w-100" @disabled($without->isEmpty())>Start profile</button>
                </form>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@include('modules.website.students._styles')
@endsection
