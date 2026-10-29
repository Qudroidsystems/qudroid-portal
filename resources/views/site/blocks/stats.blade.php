<section class="s-section is-tight" data-site-theme="{{ $b['theme'] ?? 'light' }}">
    <div class="s-wrap s-reveal">
        @if($b['heading'] ?? '')<h2 class="s-h2">{{ $b['heading'] }}</h2>@endif
        <div class="s-stats">
            @foreach($b['items'] ?? [] as $s)
                @php $v = (string) ($s['value'] ?? ''); preg_match('/^(\D*)([\d,]+(?:\.\d+)?)(.*)$/u', $v, $m); @endphp
                <div class="s-stat">
                    @if($m)<b data-count="{{ str_replace(',', '', $m[2]) }}" data-prefix="{{ $m[1] }}" data-suffix="{{ $m[3] }}" data-commas="{{ str_contains($m[2], ',') ? 1 : 0 }}">{{ $v }}</b>@else<b>{{ $v }}</b>@endif
                    <span>{{ $s['label'] ?? '' }}</span>
                </div>
            @endforeach
        </div>
    </div>
</section>
