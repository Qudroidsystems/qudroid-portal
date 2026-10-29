@extends('site.layout')
@section('title', 'Courses')

@section('content')
    <section class="s-section s-hero" data-site-theme="dark">
        <div class="s-wrap">
            <div class="s-eyebrow">Qudroid Academy</div>
            <h1 class="s-headline">Learn to build. For real.</h1>
            <p class="s-sub">Hands-on courses taught by the people who build our products.</p>
        </div>
    </section>
    <section class="s-section" data-site-theme="grey">
        <div class="s-wrap">
            @if($courses->isEmpty())
                <h2 class="s-h3">New courses are on the way.</h2>
                <p class="s-lead">Leave us your details and we will tell you when registration opens.</p>
                <div class="s-ctas"><a class="s-btn" href="{{ route('site.contact') }}">Keep me posted</a></div>
            @else
                <h2 class="s-h2">Open for registration.</h2>
                <div class="s-grid">@foreach($courses as $c) @include('site.partials.course-card', ['c' => $c]) @endforeach</div>
            @endif
        </div>
    </section>
@endsection
