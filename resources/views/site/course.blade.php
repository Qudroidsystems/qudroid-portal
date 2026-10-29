@extends('site.layout')
@section('title', $c->title)
@section('description', (string) $c->summary)
@php($money = fn ($v) => \App\Services\Website\Content::money($v, $c->currency))

@section('content')
    <div class="s-subnav">
        <div class="s-wrap">
            <b>{{ $c->title }}</b>
            <nav>
                <a href="#overview">Overview</a>
                @if($c->cohorts->isNotEmpty())<a class="s-btn is-small" href="#register">Register</a>@endif
            </nav>
        </div>
    </div>

    <section class="s-section s-detail-head" id="overview">
        <div class="s-wrap">
            <div class="s-eyebrow">{{ collect([$c->programme?->name, $c->level_label, $c->mode_label])->filter()->implode(' · ') }}</div>
            <h1 class="s-headline">{{ $c->title }}</h1>
            @if($c->summary)<p class="s-sub">{{ $c->summary }}</p>@endif
            <div class="s-ctas">
                @if($c->cohorts->isNotEmpty())<a class="s-btn" href="#register">Register now</a>@endif
                <span class="s-lead" style="margin:0;color:var(--s-ink)">{{ $money($c->cohorts->first()?->effectiveFee() ?? $c->fee) }}</span>
            </div>
        </div>
        <div class="s-wrap s-reveal">@include('site.blocks._art', ['image' => \App\Models\Website\Product::url($c->image), 'icon' => 'ri-book-open-line', 'color' => '#6366F1', 'alt' => $c->title])</div>
    </section>

    <section class="s-section is-tight">
        <div class="s-wrap s-narrow">
            <dl class="s-dl s-panel s-reveal">
                <dt>Level</dt><dd>{{ $c->level_label }}</dd>
                <dt>Delivery</dt><dd>{{ $c->mode_label }}</dd>
                @if($c->duration_weeks)<dt>Duration</dt><dd>{{ $c->duration_weeks }} weeks</dd>@endif
                <dt>Fee</dt><dd>{{ $money($c->fee) }}@if($c->installments_allowed) · pay in instalments, from {{ $money($c->min_deposit ?: 0) }} to start @endif</dd>
                @if($c->certificate_enabled)<dt>Certificate</dt><dd>Yes, on completion</dd>@endif
            </dl>
            @if($c->description)
                <div class="s-prose" style="margin-top:40px">{{ \App\Services\Website\Content::md($c->description) }}</div>
            @endif
        </div>
    </section>

    <section class="s-section" data-site-theme="grey" id="register">
        <div class="s-wrap s-narrow">
            <h2 class="s-h2">Choose your class.</h2>
            <div class="s-stagger">
            @forelse($c->cohorts as $cohort)
                <div class="s-cohort">
                    <div>
                        <b>{{ $cohort->name }}</b>
                        <small>
                            {{ $cohort->starts_on ? 'Starts ' . $cohort->starts_on->format('j M Y') : 'Start date to be announced' }}
                            @if($cohort->registration_closes_on) · register by {{ $cohort->registration_closes_on->format('j M') }}@endif
                            · {{ \App\Models\Academy\Course::MODES[$cohort->effectiveMode()] ?? ucfirst($cohort->effectiveMode()) }}
                        </small>
                    </div>
                    <div style="display:flex;align-items:center;gap:16px">
                        <span class="s-price" style="font-size:21px">{{ $money($cohort->effectiveFee()) }}</span>
                        <a class="s-btn is-small" href="{{ route('site.academy.register', [$c->slug, $cohort]) }}">Register</a>
                    </div>
                </div>
            @empty
                <p class="s-lead">There is no class open for registration right now.</p>
                <div class="s-ctas"><a class="s-btn" href="{{ route('site.contact') }}">Tell me when it opens</a></div>
            @endforelse
            </div>
            @if($c->instant_enrollment && $c->cohorts->isNotEmpty())
                <p class="s-muted" style="margin-top:16px;font-size:14px;text-align:center">Your place is confirmed when you register, and you can pay online straight away.</p>
            @endif
        </div>
    </section>
@endsection
