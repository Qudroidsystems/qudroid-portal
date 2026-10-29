@if(!empty($b['items']))
    @php
        $logo = fn ($l) => ($l['image'] ?? '')
            ? '<img src="' . e(\App\Models\Website\Product::url($l['image'])) . '" alt="' . e($l['name'] ?? '') . '" loading="lazy">'
            : '<span>' . e($l['name'] ?? '') . '</span>';
    @endphp
    <section class="s-section is-tight">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<p class="s-muted" style="font-size:17px">{{ $b['heading'] }}</p>@endif
            @if($b['scroll'] ?? false)
                <div class="s-marquee" style="--n: {{ count($b['items']) }}">
                    <div class="s-marquee-track">
                        @foreach([0, 1] as $copy)
                            <div class="s-logos is-row" @if($copy) aria-hidden="true" @endif>@foreach($b['items'] as $l){!! $logo($l) !!}@endforeach</div>
                        @endforeach
                    </div>
                </div>
            @else
                <div class="s-logos">@foreach($b['items'] as $l){!! $logo($l) !!}@endforeach</div>
            @endif
        </div>
    </section>
@endif
