@php($items = \App\Services\Website\Content::products((bool) ($b['featured_only'] ?? false)))
@if($items->isNotEmpty())
    <section class="s-section" data-site-theme="grey">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
            @if($b['intro'] ?? '')<p class="s-lead">{{ $b['intro'] }}</p>@endif
            <div class="s-grid">@foreach($items as $p) @include('site.partials.product-card', ['p' => $p]) @endforeach</div>
        </div>
    </section>
@endif
