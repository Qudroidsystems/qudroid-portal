@php($items = \App\Services\Website\Content::students((bool) ($b['featured_only'] ?? false)))
@if($items->isNotEmpty())
    <section class="s-section" data-site-theme="{{ $b['theme'] ?? 'grey' }}">
        <div class="sc-wide s-reveal">
            @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
            @if($b['intro'] ?? '')<p class="s-lead">{{ $b['intro'] }}</p>@endif
            <div class="sc-grid s-stagger" style="margin-top:40px">@foreach($items as $p) @include('site.partials.student-card', ['p' => $p]) @endforeach</div>
            <div class="s-ctas"><a class="s-more" href="{{ route('site.students') }}">Meet all our students</a></div>
        </div>
    </section>
@endif
