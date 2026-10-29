@extends('site.layout')
@section('title', $w->title)
@section('description', (string) $w->summary)
@if($w->imageUrl())@section('og_image', $w->imageUrl())@endif

@section('content')
    @if(!$w->isPublished())<div class="s-ribbon">Draft preview. Only website editors can see this.</div>@endif
    <section class="s-section s-detail-head">
        <div class="s-wrap">
            <div class="s-eyebrow">{{ collect([$w->client, $w->category, $w->year])->filter()->implode(' · ') }}</div>
            <h1 class="s-headline">{{ $w->title }}</h1>
            @if($w->summary)<p class="s-sub">{{ $w->summary }}</p>@endif
            @if($w->tags)<div class="s-tags" style="justify-content:center;margin-top:16px">@foreach($w->tags as $t)<span class="s-tag">{{ $t }}</span>@endforeach</div>@endif
        </div>
        @if($w->imageUrl())
            <img class="s-cover-img s-reveal" src="{{ $w->imageUrl() }}" alt="{{ $w->title }}">
        @else
            <div class="s-wrap s-reveal">@include('site.blocks._art', ['icon' => 'ri-briefcase-4-line', 'color' => $w->color, 'image' => null])</div>
        @endif
    </section>

    @if($w->results)
        @include('site.blocks.stats', ['b' => ['heading' => '', 'theme' => 'grey', 'items' => $w->results]])
    @endif

    @if($w->body)
        <section class="s-section">
            <div class="s-wrap s-narrow s-reveal"><div class="s-prose">{{ \App\Services\Website\Content::md($w->body) }}</div>
                @if($w->link_url)<p style="margin-top:24px"><a class="s-more" href="{{ $w->link_url }}" target="_blank" rel="noopener">Visit the project</a></p>@endif
            </div>
        </section>
    @endif

    @if($others->isNotEmpty())
        <section class="s-section" data-site-theme="grey">
            <div class="s-wrap">
                <h2 class="s-h2">More of our work.</h2>
                <div class="s-grid is-two">@foreach($others as $o) @include('site.partials.work-card', ['w' => $o]) @endforeach</div>
            </div>
        </section>
    @endif
    @include('site.blocks.cta', ['b' => ['title' => 'Have a project like this?', 'text' => '', 'button_label' => 'Talk to us', 'url' => route('site.contact'), 'theme' => 'light']])
@endsection
