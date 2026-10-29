@extends('site.layout')
@section('title', 'Our work')

@section('content')
    <section class="s-section s-hero" data-site-theme="dark">
        <div class="s-wrap">
            <div class="s-eyebrow">Our work</div>
            <h1 class="s-headline">Built for clients. Built to last.</h1>
            <p class="s-sub">A selection of projects we have designed, built and launched.</p>
        </div>
    </section>
    <section class="s-section" data-site-theme="grey">
        <div class="s-wrap">
            @if($works->isEmpty())
                <h2 class="s-h3">Case studies are on their way.</h2>
                <p class="s-lead">In the meantime, tell us about your project.</p>
                <div class="s-ctas"><a class="s-btn" href="{{ route('site.contact') }}">Start a project</a></div>
            @else
                <div class="s-stagger s-grid {{ in_array($works->count(), [2, 4]) ? 'is-two' : '' }}">@foreach($works as $w) @include('site.partials.work-card', ['w' => $w]) @endforeach</div>
            @endif
        </div>
    </section>
    @include('site.blocks.cta', ['b' => ['title' => 'Your project could be next.', 'text' => '', 'button_label' => 'Talk to us', 'url' => route('site.contact'), 'theme' => 'light']])
@endsection
