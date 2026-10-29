@php($tiles = \App\Services\Website\Content::tiles($b))
@if($tiles)
    <section class="s-tiles">
        @foreach($tiles as $t)
            <div class="s-tile s-reveal" data-site-theme="{{ $t['theme'] ?? 'grey' }}">
                @if($t['url'] ?? '')<a class="s-cover" href="{{ $t['url'] }}" aria-label="{{ $t['title'] }}"></a>@endif
                <h3>{{ $t['title'] ?? '' }}</h3>
                @if($t['subtitle'] ?? '')<p>{{ $t['subtitle'] }}</p>@endif
                <div class="s-ctas">
                    @if(($t['url'] ?? '') && ($t['link_label'] ?? ''))<a class="s-btn is-small" href="{{ $t['url'] }}">{{ $t['link_label'] }}</a>@endif
                    @if($t['secondary'] ?? null)<a class="s-more" href="{{ $t['secondary']['url'] }}">{{ $t['secondary']['label'] }}</a>@endif
                </div>
                <div class="s-tile-art">@include('site.blocks._art', ['image' => $t['image'] ?? null, 'icon' => $t['icon'] ?? null, 'color' => $t['color'] ?? null, 'alt' => $t['title'] ?? ''])</div>
            </div>
        @endforeach
        @if(count($tiles) % 2 === 1 && count($tiles) > 1)
            <div class="s-tile s-reveal" data-site-theme="grey" style="justify-content:center">
                <h3>And more.</h3><p>See everything we make.</p>
                <div class="s-ctas"><a class="s-more" href="{{ ($b['source'] ?? '') === 'courses' ? route('site.academy') : (($b['source'] ?? '') === 'works' ? route('site.works') : route('site.products')) }}">View all</a></div>
            </div>
        @endif
    </section>
@endif
