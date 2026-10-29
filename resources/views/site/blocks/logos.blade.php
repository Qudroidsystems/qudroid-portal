@if(!empty($b['items']))
    <section class="s-section is-tight">
        <div class="s-wrap s-reveal">
            @if($b['heading'] ?? '')<p class="s-muted" style="font-size:17px">{{ $b['heading'] }}</p>@endif
            <div class="s-logos">
                @foreach($b['items'] as $l)
                    @if($l['image'] ?? '')<img src="{{ \App\Models\Website\Product::url($l['image']) }}" alt="{{ $l['name'] ?? '' }}" loading="lazy">@else<span>{{ $l['name'] ?? '' }}</span>@endif
                @endforeach
            </div>
        </div>
    </section>
@endif
