<section class="s-section" data-site-theme="{{ $b['theme'] ?? 'light' }}">
    <div class="s-wrap s-reveal">
        @if($b['eyebrow'] ?? '')<div class="s-eyebrow">{{ $b['eyebrow'] }}</div>@endif
        @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
        @if($b['intro'] ?? '')<p class="s-lead">{{ $b['intro'] }}</p>@endif
        <div class="s-features s-stagger">
            @foreach($b['items'] ?? [] as $f)
                <div class="s-feature">
                    @if($f['icon'] ?? '')<i class="{{ preg_match('/^ri-[a-z0-9-]+$/', $f['icon']) ? $f['icon'] : 'ri-magic-line' }}" aria-hidden="true"></i>@endif
                    <h4>{{ $f['title'] ?? '' }}</h4>
                    <p>{{ $f['text'] ?? '' }}</p>
                </div>
            @endforeach
        </div>
    </div>
</section>
