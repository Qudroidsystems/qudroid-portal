<section class="s-section is-tight" data-site-theme="{{ $b['theme'] ?? 'light' }}">
    <div class="s-wrap s-reveal">
        @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
        <div class="s-stats">
            @foreach($b['items'] ?? [] as $s)
                <div class="s-stat"><b>{{ $s['value'] ?? '' }}</b><span>{{ $s['label'] ?? '' }}</span></div>
            @endforeach
        </div>
    </div>
</section>
