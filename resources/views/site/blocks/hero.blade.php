<section class="s-section s-hero" data-site-theme="{{ $b['theme'] ?? 'light' }}">
    <div class="s-wrap">
        @if($b['eyebrow'] ?? '')<div class="s-eyebrow">{{ $b['eyebrow'] }}</div>@endif
        <h1 class="s-headline">{{ $b['title'] ?? '' }}</h1>
        @if($b['subtitle'] ?? '')<p class="s-sub">{{ $b['subtitle'] }}</p>@endif
        @include('site.blocks._ctas', ['primary' => ['label' => $b['primary_label'] ?? '', 'url' => $b['primary_url'] ?? ''], 'secondary' => ['label' => $b['secondary_label'] ?? '', 'url' => $b['secondary_url'] ?? '']])
    </div>
    @if($b['image'] ?? '')
        <div class="s-hero-art s-wrap s-reveal"><img src="{{ \App\Models\Website\Product::url($b['image']) }}" alt=""></div>
    @elseif(in_array($b['theme'] ?? 'light', ['dark', 'blue']) && ($first ?? false))
        <div class="s-glow s-reveal" aria-hidden="true"><x-qlouds-logo animate size="64px" :rest="false" /></div>
    @endif
</section>
