@php
    $theme = $b['theme'] ?? 'light';
    $words = collect(explode(',', (string) ($b['rotating'] ?? '')))->map(fn ($w) => trim($w))->filter()->take(8)->values();
@endphp
<section class="s-section s-hero {{ in_array($theme, ['dark', 'blue']) ? 'has-orbs' : '' }}" data-site-theme="{{ $theme }}">
    @if(in_array($theme, ['dark', 'blue']))
        <div class="s-orbs" aria-hidden="true"><span></span><span></span><span></span><i class="s-grid-lines"></i></div>
    @endif
    <div class="s-wrap s-hero-in">
        @if($b['eyebrow'] ?? '')<div class="s-eyebrow">{{ $b['eyebrow'] }}</div>@endif
        <h1 class="s-headline">{{ $b['title'] ?? '' }}@if($words->isNotEmpty())
            <span class="s-rotate" data-words='@json($words)' aria-label="{{ $words->implode(', ') }}"><span class="s-rotate-word">{{ $words->first() }}</span></span>@endif</h1>
        @if($b['subtitle'] ?? '')<p class="s-sub">{{ $b['subtitle'] }}</p>@endif
        @include('site.blocks._ctas', ['primary' => ['label' => $b['primary_label'] ?? '', 'url' => $b['primary_url'] ?? ''], 'secondary' => ['label' => $b['secondary_label'] ?? '', 'url' => $b['secondary_url'] ?? '']])
    </div>
    @if($b['image'] ?? '')
        <div class="s-hero-art s-wrap s-reveal"><img src="{{ \App\Models\Website\Product::url($b['image']) }}" alt=""></div>
    @elseif(in_array($theme, ['dark', 'blue']) && ($first ?? false))
        <div class="s-glow s-reveal" aria-hidden="true"><x-qlouds-logo animate size="64px" :rest="false" /></div>
    @endif
</section>
