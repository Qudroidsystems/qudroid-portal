@extends('site.layout')
@section('title', $kind === 'client' ? 'What our clients say' : ($kind === 'student' ? 'What our students say' : 'Testimonials'))

@section('content')
@php
    $hero = ($kind === 'client' ? $clients : ($kind === 'student' ? $students : $students->concat($clients)))->firstWhere('featured', true);
@endphp
    <section class="s-section s-hero" data-site-theme="dark">
        <div class="s-wrap">
            <div class="s-eyebrow">Testimonials</div>
            <h1 class="s-headline">{{ $kind === 'client' ? 'Trusted by teams we build for.' : ($kind === 'student' ? 'Stories from our students.' : 'What people say about us.') }}</h1>
            @if($average)<p class="s-sub"><span class="sc-stars">{{ str_repeat('★', (int) round($average)) }}</span> {{ $average }} out of 5 on average</p>@endif
            <nav class="sc-tabs" aria-label="Whose testimonials">
                <a href="{{ route('site.testimonials') }}" class="{{ $kind ? '' : 'is-on' }}">Everyone</a>
                <a href="{{ route('site.testimonials.kind', 'students') }}" class="{{ $kind === 'student' ? 'is-on' : '' }}">Students</a>
                <a href="{{ route('site.testimonials.kind', 'clients') }}" class="{{ $kind === 'client' ? 'is-on' : '' }}">Clients</a>
            </nav>
        </div>
    </section>

    @if($hero)
        <section class="s-section is-tight">
            <div class="s-wrap sc-hero-quote">
                @if($hero->rating)<div class="sc-stars">{{ str_repeat('★', $hero->rating) }}</div>@endif
                <blockquote>“{{ $hero->quote }}”</blockquote>
                <div class="sc-who">
                    <span class="sc-avatar {{ $hero->kind === 'client' && $hero->organisation ? 'is-logo' : '' }}">@if($hero->photoUrl())<img src="{{ $hero->photoUrl() }}" alt="">@else{{ $hero->initials() }}@endif</span>
                    <span><b>{{ $hero->name }}</b><br><small class="s-muted">{{ $hero->byline() }}</small></span>
                </div>
            </div>
        </section>
    @endif

    @foreach(array_filter(['student' => $kind !== 'client' ? $students : collect(), 'client' => $kind !== 'student' ? $clients : collect()], fn ($l) => $l->isNotEmpty()) as $k => $list)
        <section class="s-section" data-site-theme="grey" style="padding:64px 0">
            <div class="sc-wide">
                @unless($kind)
                    <div class="sc-section-head"><h2 class="s-h2">{{ $k === 'client' ? 'Clients.' : 'Students.' }}</h2>
                        <a class="s-more" href="{{ route('site.testimonials.kind', $k . 's') }}">Only {{ $k === 'client' ? 'clients' : 'students' }}</a></div>
                @endunless
                <div class="sc-wall" @if($kind) style="margin-top:0" @endif>@foreach($list->reject(fn ($t) => $hero && $t->id === $hero->id) as $t) @include('site.partials.quote-card', ['t' => $t, 'profiles' => $profiles]) @endforeach</div>
            </div>
        </section>
    @endforeach

    @if($students->isEmpty() && $clients->isEmpty())
        <section class="s-section" data-site-theme="grey">
            <div class="s-wrap"><h2 class="s-h3">Stories are on their way.</h2></div>
        </section>
    @endif

    @if($kind === 'client')
        @include('site.blocks.cta', ['b' => ['title' => 'Let’s build yours.', 'text' => 'Tell us about your project.', 'button_label' => 'Start a project', 'url' => route('site.contact'), 'theme' => 'dark']])
    @else
        @include('site.blocks.cta', ['b' => ['title' => 'Write the next story.', 'text' => 'Learn by building real projects with us.', 'button_label' => 'See our courses', 'url' => route('site.academy'), 'theme' => 'dark']])
    @endif
@endsection
