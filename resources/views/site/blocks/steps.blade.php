@if(!empty($b['items']))
    <section class="s-section" data-site-theme="{{ $b['theme'] ?? 'light' }}">
        <div class="s-wrap">
            <div class="s-reveal">
                @if($b['eyebrow'] ?? '')<div class="s-eyebrow">{{ $b['eyebrow'] }}</div>@endif
                @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
                @if($b['intro'] ?? '')<p class="s-lead">{{ $b['intro'] }}</p>@endif
            </div>
            <ol class="s-steps s-stagger" style="--n: {{ count($b['items']) }}">
                @foreach($b['items'] as $i => $st)
                    <li class="s-step">
                        <span class="s-step-no">{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}</span>
                        @if(preg_match('/^ri-[a-z0-9-]+$/', $st['icon'] ?? ''))<i class="{{ $st['icon'] }}" aria-hidden="true"></i>@endif
                        <h4>{{ $st['title'] ?? '' }}</h4>
                        @if($st['text'] ?? '')<p>{{ $st['text'] }}</p>@endif
                    </li>
                @endforeach
            </ol>
        </div>
    </section>
@endif
