@extends('site.layout')
@section('title', $p->name())
@section('description', trim(($p->headline ? $p->headline . '. ' : '') . \Illuminate\Support\Str::limit((string) $p->bio, 140)))
@if($p->photoUrl())@section('og_image', $p->photoUrl())@endif

@section('content')
@php
    $pal = [['#0071e3', '#6e3aff'], ['#ff375f', '#ff9f0a'], ['#30d158', '#0a84ff'], ['#bf5af2', '#5e5ce6'], ['#ff9f0a', '#ff375f'], ['#64d2ff', '#0071e3']];
    [$c1, $c2] = $pal[$p->id % count($pal)];
    $photo = $p->photoUrl();
@endphp
    <section class="s-section s-hero" data-site-theme="dark" style="padding:64px 0 80px">
        <div class="s-wrap">
            <div style="text-align:left;margin-bottom:28px"><a class="s-more is-back" href="{{ route('site.students') }}">Our students</a></div>
            <div class="sc-profile" style="--c1: {{ $c1 }}; --c2: {{ $c2 }}">
                <div class="sc-photo {{ $photo ? '' : 'is-initials' }}">@if($photo)<img src="{{ $photo }}" alt="{{ $p->name() }}">@else{{ $p->initials() }}@endif</div>
                <div>
                    @if($p->open_to_work)<span class="sc-badge" style="margin-bottom:12px"><i class="ri-briefcase-line"></i> Open to work</span>@endif
                    <h1>{{ $p->name() }}</h1>
                    @if($p->headline)<p class="sc-headline">{{ $p->headline }}</p>@endif
                    <div class="sc-meta">
                        @if($p->location)<span><i class="ri-map-pin-line"></i> {{ $p->location }}</span>@endif
                        @if($studies->isNotEmpty())<span><i class="ri-graduation-cap-line"></i> {{ $studies->first()['course']->title }}@if($studies->first()['cohort']) · {{ $studies->first()['cohort'] }}@endif</span>@endif
                    </div>
                    @if($p->linkList())
                        <div class="sc-meta">@foreach($p->linkList() as [$k, $label, $icon, $url])<a class="sc-linkbtn" href="{{ $url }}" target="_blank" rel="noopener nofollow"><i class="{{ $icon }}"></i> {{ $label }}</a>@endforeach</div>
                    @endif
                </div>
            </div>
        </div>
    </section>

    <section class="s-section" data-site-theme="grey" style="padding:56px 0">
        <div class="s-wrap">
            <div class="sc-two">
                <div class="sc-panel">
                    <h2>About</h2>
                    <p>{{ $p->bio ?: 'A student of ' . config('qudroid.company') . ' Academy.' }}</p>
                    @if($p->skills)<div class="s-tags" style="margin-top:14px">@foreach($p->skills as $s)<span class="s-tag">{{ $s }}</span>@endforeach</div>@endif
                </div>
                <div class="sc-panel">
                    <h2>Studied with us</h2>
                    @forelse($studies as $st)
                        <div class="sc-study">
                            <i class="{{ $st['completed'] ? 'ri-award-fill' : 'ri-book-open-line' }}"></i>
                            <div>
                                <b><a href="{{ route('site.academy.course', $st['course']->slug) }}" style="color:inherit">{{ $st['course']->title }}</a></b>
                                <small>{{ collect([$st['cohort'], $st['year']])->filter()->implode(' · ') }} · {{ $st['completed'] ? 'Completed' : 'Studying now' }}</small>
                                @if($st['certificate'])<div><a href="{{ $st['certificate'] }}" style="font-size:14px">Verify certificate</a></div>@endif
                            </div>
                        </div>
                    @empty
                        <p class="s-muted">{{ config('qudroid.company') }} Academy</p>
                    @endforelse
                </div>
            </div>

            @if($p->projects->isNotEmpty())
                <h2 class="s-h2" style="margin-top:72px">Projects.</h2>
                <div class="sc-projects">
                    @foreach($p->projects as $pr)
                        <article class="sc-project">
                            <div class="sc-shot">@if($pr->imageUrl())<img src="{{ $pr->imageUrl() }}" alt="{{ $pr->title }}" loading="lazy">@else<i class="ri-code-box-line"></i>@endif</div>
                            <div class="sc-project-body">
                                @if($pr->course)<span class="s-card-kicker">{{ $pr->course->title }}</span>@endif
                                <h3>{{ $pr->title }}</h3>
                                @if($pr->summary)<p>{{ $pr->summary }}</p>@endif
                                @if($pr->tags)<div class="s-tags">@foreach($pr->tags as $t)<span class="s-tag">{{ $t }}</span>@endforeach</div>@endif
                                @if($pr->url || $pr->repo_url)
                                    <div class="sc-actions">
                                        @if($pr->url)<a class="is-live" href="{{ $pr->url }}" target="_blank" rel="noopener nofollow"><i class="ri-external-link-line"></i> View live</a>@endif
                                        @if($pr->repo_url)<a class="is-code" href="{{ $pr->repo_url }}" target="_blank" rel="noopener nofollow"><i class="ri-github-fill"></i> Code</a>@endif
                                    </div>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif

            @if($quotes->isNotEmpty())
                <h2 class="s-h2" style="margin-top:72px">What {{ \Illuminate\Support\Str::before($p->name(), ' ') }} says.</h2>
                <div class="sc-wall" style="columns:2 300px">@foreach($quotes as $t) @include('site.partials.quote-card', ['t' => $t]) @endforeach</div>
            @endif
        </div>
    </section>

    @if($more->isNotEmpty())
        <section class="s-section is-tight">
            <div class="s-wrap">
                <div class="sc-section-head"><h2 class="s-h2">More students.</h2><a class="s-more" href="{{ route('site.students') }}">See everyone</a></div>
                <div class="sc-grid" style="margin-top:32px">@foreach($more as $o) @include('site.partials.student-card', ['p' => $o->loadMissing(['student.enrollments' => fn ($e) => $e->whereIn('status', ['active', 'completed'])->with('course', 'cohort')])->loadCount('projects')]) @endforeach</div>
            </div>
        </section>
    @endif
@endsection
