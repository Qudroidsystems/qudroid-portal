<section class="s-section" data-site-theme="{{ $b['theme'] ?? 'light' }}">
    <div class="s-wrap">
        <div class="s-split s-reveal {{ ($b['image_side'] ?? 'right') === 'left' ? 'is-left' : '' }}">
            <div>
                @if($b['eyebrow'] ?? '')<div class="s-eyebrow">{{ $b['eyebrow'] }}</div>@endif
                <h2 class="s-h2">{{ $b['heading'] ?? '' }}</h2>
                <div class="s-prose" style="margin-top:20px;color:var(--s-ink-2)">{{ \App\Services\Website\Content::md($b['body'] ?? '') }}</div>
                @if(($b['link_label'] ?? '') && ($b['url'] ?? ''))<a class="s-more" href="{{ $b['url'] }}">{{ $b['link_label'] }}</a>@endif
            </div>
            <div class="s-split-art">@include('site.blocks._art', ['image' => \App\Models\Website\Product::url($b['image'] ?? null), 'icon' => 'ri-apps-2-line', 'color' => '#0EA5E9'])</div>
        </div>
    </div>
</section>
