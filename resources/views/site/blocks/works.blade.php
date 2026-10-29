@php($items = \App\Services\Website\Content::works((bool) ($b['featured_only'] ?? false)))
@if($items->isNotEmpty())
    <section class="s-section">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
            @if($b['intro'] ?? '')<p class="s-lead">{{ $b['intro'] }}</p>@endif
            <div class="s-grid {{ $items->count() === 2 || $items->count() === 4 ? 'is-two' : '' }}">@foreach($items as $w) @include('site.partials.work-card', ['w' => $w]) @endforeach</div>
            <div class="s-ctas"><a class="s-more" href="{{ route('site.works') }}">See all our work</a></div>
        </div>
    </section>
@endif
