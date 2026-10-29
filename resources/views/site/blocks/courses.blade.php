@php($items = \App\Services\Website\Content::courses()->take(6))
@if($items->isNotEmpty())
    <section class="s-section" data-site-theme="grey">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
            @if($b['intro'] ?? '')<p class="s-lead">{{ $b['intro'] }}</p>@endif
            <div class="s-grid">@foreach($items as $c) @include('site.partials.course-card', ['c' => $c]) @endforeach</div>
            <div class="s-ctas"><a class="s-more" href="{{ route('site.academy') }}">All courses</a></div>
        </div>
    </section>
@endif
