<section class="s-section" data-site-theme="{{ $b['theme'] ?? 'grey' }}">
    <div class="s-wrap s-reveal">
        <h2 class="s-h2">{{ $b['title'] ?? '' }}</h2>
        @if($b['text'] ?? '')<p class="s-lead">{{ $b['text'] }}</p>@endif
        @include('site.blocks._ctas', ['primary' => ['label' => $b['button_label'] ?? '', 'url' => $b['url'] ?? ''], 'secondary' => []])
    </div>
</section>
