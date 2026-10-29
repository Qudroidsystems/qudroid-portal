@extends('site.layout')
@section('title', $p->name)
@section('description', (string) ($p->summary ?: $p->tagline))
@if($p->imageUrl())@section('og_image', $p->imageUrl())@endif

@section('content')
    <div class="s-subnav">
        <div class="s-wrap">
            <b>{{ $p->name }}</b>
            <nav>
                <a href="#overview">Overview</a>
                @if($p->features)<a href="#features">Features</a>@endif
                @if($p->link_url)<a class="s-btn is-small" href="{{ $p->link_url }}">{{ $p->link_label ?: 'Get started' }}</a>@else<a class="s-btn is-small" href="{{ route('site.contact') }}">Talk to us</a>@endif
            </nav>
        </div>
    </div>
    @if(!$p->isPublished())<div class="s-ribbon">Draft preview. Only website editors can see this.</div>@endif

    <section class="s-section s-detail-head" id="overview">
        <div class="s-wrap">
            @if($p->category)<div class="s-eyebrow">{{ $p->category }}</div>@endif
            <h1 class="s-headline">{{ $p->name }}</h1>
            @if($p->tagline)<p class="s-sub">{{ $p->tagline }}</p>@endif
            @if($p->price_label)<p class="s-lead" style="color:var(--s-ink)">{{ $p->price_label }}</p>@endif
            <div class="s-ctas">
                <a class="s-btn" href="{{ $p->link_url ?: route('site.contact') }}">{{ $p->link_label ?: 'Talk to us' }}</a>
                @if($p->features)<a class="s-more" href="#features">See what it does</a>@endif
            </div>
        </div>
        @if($p->imageUrl())
            <img class="s-cover-img s-reveal" src="{{ $p->imageUrl() }}" alt="{{ $p->name }}">
        @else
            <div class="s-wrap s-reveal">@include('site.blocks._art', ['icon' => $p->icon, 'color' => $p->color, 'image' => null])</div>
        @endif
    </section>

    @if($p->summary || $p->body)
        <section class="s-section is-tight">
            <div class="s-wrap s-narrow s-reveal">
                @if($p->summary)<p class="s-lead" style="color:var(--s-ink);margin-bottom:32px;text-align:center">{{ $p->summary }}</p>@endif
                <div class="s-prose">{{ \App\Services\Website\Content::md($p->body) }}</div>
            </div>
        </section>
    @endif

    @if($p->features)
        <div id="features">
            @include('site.blocks.features', ['b' => ['eyebrow' => '', 'heading' => 'What you get.', 'intro' => '', 'theme' => 'grey', 'items' => $p->features]])
        </div>
    @endif

    @if($others->isNotEmpty())
        <section class="s-section">
            <div class="s-wrap">
                <h2 class="s-h2">More from us.</h2>
                <div class="s-grid">@foreach($others as $o) @include('site.partials.product-card', ['p' => $o]) @endforeach</div>
            </div>
        </section>
    @endif
@endsection
