@extends('layouts.master')

@section('content')
@php
    $tone = ['draft' => ['secondary', 'ri-eye-off-line'], 'pending' => ['warning', 'ri-time-line'], 'published' => ['success', 'ri-global-line'], 'hidden' => ['danger', 'ri-forbid-line']][$p->status ?? 'draft'];
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="My public profile" icon="ri-user-star-line" subtitle="Show your work on our website. Only what you fill in here is shown, and only if you choose to share it." :back="route('academy.my')" back-label="My academy">
        @if($p->isPublic())
            <x-slot:actions><a class="cb-hero-btn" href="{{ route('site.students.show', $p) }}" target="_blank"><i class="ri-external-link-line me-1"></i>See my page</a></x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')
    @if($errors->any())<div class="alert alert-danger"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif

    <div class="row g-3">
        <div class="col-xl-8">
            <form method="POST" action="{{ route('academy.my.profile.update') }}" enctype="multipart/form-data">
                @csrf
                <x-cb.card title="About me" icon="ri-user-3-line">
                    <div class="sp-status mb-3 text-{{ $tone[0] }}"><i class="{{ $tone[1] }}"></i>
                        <div class="text-body"><strong>{{ $p->status_label }}</strong>
                            <div class="small text-muted">{{ match ($p->status ?? 'draft') {
                                'pending' => 'Staff will check your profile and put it on the website.',
                                'published' => 'Your profile and projects are on the website. Changes show straight away.',
                                'hidden' => 'Staff have taken your profile off the website for now. Contact the academy if you have questions.',
                                default => 'Your profile is private. Tick "Show my profile on the website" when you are ready.',
                            } }}</div>
                        </div>
                    </div>
                    @include('modules.website.students._fields', ['p' => $p])
                    <hr>
                    <div class="form-check form-switch mb-1">
                        <input type="hidden" name="share" value="0">
                        <input class="form-check-input" type="checkbox" name="share" value="1" id="share" @checked($p->consented_at)>
                        <label class="form-check-label fw-semibold" for="share">Show my profile on the website</label>
                    </div>
                    <div class="form-text mb-3">I agree that {{ config('qudroid.company') }} may show my name, photo, the details above, my courses and class, and my projects on its public website. I can turn this off at any time and my page comes down straight away.</div>
                    <button class="btn btn-primary">Save</button>
                </x-cb.card>
            </form>

            @include('modules.website.students._projects', [
                'p' => $p, 'project' => $project, 'courses' => $courses,
                'routes' => [
                    'store' => fn () => route('academy.my.profile.projects.store'),
                    'update' => fn ($pr) => route('academy.my.profile.projects.update', $pr),
                    'destroy' => fn ($pr) => route('academy.my.profile.projects.destroy', $pr),
                ],
                'editUrl' => fn ($pr) => $pr ? route('academy.my.profile', ['project' => $pr->id]) . '#projectForm' : route('academy.my.profile'),
            ])
        </div>

        <div class="col-xl-4">
            <x-cb.card title="Write a testimonial" icon="ri-chat-quote-line">
                <p class="text-muted small">Tell future students what learning with us is like. Staff publish it on the website after a quick look.</p>
                <form method="POST" action="{{ route('academy.my.testimonial') }}">
                    @csrf
                    <div class="mb-2"><label class="form-label">Your words</label><textarea name="quote" class="form-control" rows="5" required minlength="20" maxlength="1500">{{ old('quote') }}</textarea></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small">Course</label><select name="course_id" class="form-select form-select-sm"><option value="">—</option>@foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id') == $c->id)>{{ $c->title }}</option>@endforeach</select></div>
                        <div class="col-6"><label class="form-label small">Rating</label><select name="rating" class="form-select form-select-sm"><option value="">—</option>@foreach([5, 4, 3, 2, 1] as $r)<option value="{{ $r }}" @selected(old('rating') == $r)>{{ str_repeat('★', $r) }}</option>@endforeach</select></div>
                        <div class="col-12"><label class="form-label small">What you do now (optional)</label><input name="role" class="form-control form-control-sm" maxlength="120" value="{{ old('role') }}" placeholder="e.g. Junior developer at Acme"></div>
                    </div>
                    <div class="form-check small mb-3"><input class="form-check-input" type="checkbox" name="consent" value="1" id="tConsent" required><label class="form-check-label" for="tConsent">{{ config('qudroid.company') }} may show this with my name and photo on its website.</label></div>
                    <button class="btn btn-soft-primary w-100">Send</button>
                </form>
                @if($testimonials->isNotEmpty())
                    <hr><h6 class="small text-uppercase text-muted">Sent</h6>
                    @foreach($testimonials as $t)
                        <div class="small border-top py-2">“{{ \Illuminate\Support\Str::limit($t->quote, 110) }}”
                            <div><span class="badge bg-{{ ['pending' => 'warning', 'published' => 'success', 'hidden' => 'secondary'][$t->status] }}-subtle text-{{ ['pending' => 'warning', 'published' => 'success', 'hidden' => 'secondary'][$t->status] }}">{{ \App\Models\Website\Testimonial::STATUSES[$t->status] }}</span> <span class="text-muted">{{ $t->created_at->format('j M Y') }}</span></div></div>
                    @endforeach
                @endif
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@include('modules.website.students._styles')
@endsection
