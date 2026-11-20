@extends('site.layout')
@section('title', 'Yearbook')
@section('description', 'The graduating classes of ' . config('qudroid.company') . ' Academy.')

@section('content')
@php $pal = [['#0071e3', '#6e3aff'], ['#ff375f', '#ff9f0a'], ['#30d158', '#0a84ff'], ['#bf5af2', '#5e5ce6'], ['#ff9f0a', '#ff375f'], ['#64d2ff', '#0071e3']]; @endphp
    <section class="s-section s-hero" data-site-theme="dark">
        <div class="s-wrap">
            <div class="s-eyebrow">Academy</div>
            <h1 class="s-headline">Yearbook.</h1>
            <p class="s-sub">Every class that has graduated with us, and the people in it.</p>
            @if($totals['classes'])
                <div class="sc-stats">
                    <div><b>{{ $totals['classes'] }}</b><span>{{ \Illuminate\Support\Str::plural('class', $totals['classes']) }}</span></div>
                    <div><b>{{ $totals['graduates'] }}</b><span>{{ \Illuminate\Support\Str::plural('graduate', $totals['graduates']) }}</span></div>
                </div>
            @endif
            @if($allYears->count() > 1)
                <nav class="yb-years" aria-label="Year">
                    <a href="{{ route('site.yearbook') }}" class="{{ $year ? '' : 'is-on' }}">All years</a>
                    @foreach($allYears as $y)<a href="{{ route('site.yearbook', ['year' => $y]) }}" class="{{ (string) $year === (string) $y ? 'is-on' : '' }}">{{ $y }}</a>@endforeach
                </nav>
            @endif
        </div>
    </section>

    <section class="s-section" data-site-theme="grey" style="padding-top:64px">
        <div class="sc-wide" style="max-width:1100px">
            @forelse($years as $y => $classes)
                <h2 class="yb-year">{{ is_numeric($y) ? 'Class of ' . $y : $y }}</h2>
                @foreach($classes as $c)
                    <article class="yb-class s-reveal">
                        @if($c->yearbookPhotoUrl())<div class="yb-photo"><img src="{{ $c->yearbookPhotoUrl() }}" alt="{{ $c->name }}" loading="lazy"></div>@endif
                        <div class="yb-body">
                            <div class="yb-head">
                                <div><a class="s-card-kicker" href="{{ route('site.academy.course', $c->course->slug) }}">{{ $c->course->title }}</a><h3>{{ $c->name }}</h3></div>
                                <span class="yb-count">@if($c->graduates_count){{ $c->graduates_count }} {{ \Illuminate\Support\Str::plural('graduate', $c->graduates_count) }}@endif{{ $c->ends_on ? ' · Graduated ' . $c->ends_on->format('F Y') : '' }}</span>
                            </div>
                            @if($c->yearbook_note)<p class="yb-note">{{ $c->yearbook_note }}</p>@endif
                            @if($c->graduates->isNotEmpty())
                                <div class="yb-faces">
                                    @foreach($c->graduates as $p)
                                        @php([$c1, $c2] = $pal[$p->id % count($pal)])
                                        <a class="yb-face" href="{{ route('site.students.show', $p) }}" style="--c1: {{ $c1 }}; --c2: {{ $c2 }}">
                                            <div class="sc-photo {{ $p->photoUrl() ? '' : 'is-initials' }}">@if($p->photoUrl())<img src="{{ $p->photoUrl() }}" alt="{{ $p->name() }}" loading="lazy">@else{{ $p->initials() }}@endif</div>
                                            <b>{{ $p->name() }}</b>
                                            @if($p->headline)<small>{{ \Illuminate\Support\Str::limit($p->headline, 48) }}</small>@endif
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    </article>
                @endforeach
            @empty
                <h2 class="s-h3">The yearbook is on its way.</h2>
                <p class="s-lead">Our first classes will appear here soon.</p>
            @endforelse
        </div>
    </section>

    @include('site.blocks.cta', ['b' => ['title' => 'Be in the next one.', 'text' => 'Join a class and graduate with work to show.', 'button_label' => 'See our courses', 'url' => route('site.academy'), 'theme' => 'dark']])
@endsection
