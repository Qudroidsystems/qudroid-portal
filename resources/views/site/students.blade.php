@extends('site.layout')
@section('title', 'Our students')
@section('description', 'Meet the students of ' . config('qudroid.company') . ' Academy and the projects they have built.')

@section('content')
    <section class="s-section s-hero" data-site-theme="dark">
        <div class="s-wrap">
            <div class="s-eyebrow">Academy</div>
            <h1 class="s-headline">{{ $show === 'alumni' ? 'Meet our alumni.' : 'Meet our students.' }}</h1>
            <p class="s-sub">{{ $show === 'alumni' ? 'Graduates of our academy, and the real projects they built with us.' : 'Engineers, designers and analysts in the making, and the real projects they have shipped.' }}</p>
            @if($stats['students'])
                <div class="sc-stats">
                    <div><b>{{ $stats['students'] }}</b><span>{{ \Illuminate\Support\Str::plural('student', $stats['students']) }}</span></div>
                    <div><b>{{ $stats['projects'] }}</b><span>{{ \Illuminate\Support\Str::plural('project', $stats['projects']) }}</span></div>
                    <div><b>{{ $stats['courses'] }}</b><span>{{ \Illuminate\Support\Str::plural('course', $stats['courses']) }}</span></div>
                </div>
            @endif
        </div>
    </section>

    @if($stats['students'])
        <div class="sc-bar">
            <div class="s-wrap">
                <nav class="sc-seg" aria-label="Who to show">
                    @foreach([null => 'Everyone', 'alumni' => 'Alumni', 'current' => 'Studying now'] as $k => $label)
                        <a class="{{ $show === ($k ?: null) ? 'is-on' : '' }}" href="{{ route('site.students', array_filter(['show' => $k ?: null, 'course' => $course?->slug, 'q' => $q])) }}">{{ $label }}</a>
                    @endforeach
                </nav>
                <nav class="sc-chips" aria-label="Filter by course">
                    <a class="sc-chip {{ $course ? '' : 'is-on' }}" href="{{ route('site.students', array_filter(['show' => $show, 'q' => $q])) }}">All courses</a>
                    @foreach($courses as $c)<a class="sc-chip {{ $course?->id === $c->id ? 'is-on' : '' }}" href="{{ route('site.students', array_filter(['show' => $show, 'course' => $c->slug, 'q' => $q])) }}">{{ $c->title }}</a>@endforeach
                </nav>
                @if($hasYearbook)<a class="s-more" href="{{ route('site.yearbook') }}" style="white-space:nowrap;font-size:14px">Yearbook</a>@endif
                <form class="sc-search" method="GET" role="search">
                    @if($show)<input type="hidden" name="show" value="{{ $show }}">@endif
                    @if($course)<input type="hidden" name="course" value="{{ $course->slug }}">@endif
                    <i class="ri-search-line"></i><input name="q" value="{{ $q }}" placeholder="Search names or skills" aria-label="Search students">
                </form>
            </div>
        </div>
    @endif

    <section class="s-section" data-site-theme="grey" style="padding-top:48px">
        <div class="sc-wide">
            @if($profiles->isEmpty())
                <h2 class="s-h3">{{ $stats['students'] ? 'No students match that.' : 'Student profiles are on their way.' }}</h2>
                <p class="s-lead">{{ $stats['students'] ? 'Try another course or search.' : 'Our students will share their work here soon.' }}</p>
            @else
                <div class="sc-grid">@foreach($profiles as $p) @include('site.partials.student-card', ['p' => $p]) @endforeach</div>
            @endif
        </div>
    </section>

    @if($quotes->isNotEmpty())
        <section class="s-section">
            <div class="s-wrap">
                <div class="sc-section-head"><h2 class="s-h2">In their words.</h2><a class="s-more" href="{{ route('site.testimonials.kind', 'students') }}">All student stories</a></div>
                <div class="sc-wall">@foreach($quotes as $t) @include('site.partials.quote-card', ['t' => $t]) @endforeach</div>
            </div>
        </section>
    @endif

    @include('site.blocks.cta', ['b' => ['title' => 'Your project could be here next.', 'text' => 'Join a class, learn by building, and graduate with work to show.', 'button_label' => 'See our courses', 'url' => route('site.academy'), 'theme' => 'dark']])
@endsection
