<a class="s-card" href="{{ route('site.products.show', $p) }}">
    <div class="s-card-art">@include('site.blocks._art', ['image' => $p->imageUrl(), 'icon' => $p->icon, 'color' => $p->color, 'class' => 'is-card', 'alt' => $p->name])</div>
    <div class="s-card-body">
        @if($p->category)<span class="s-card-kicker">{{ $p->category }}</span>@endif
        <h3>{{ $p->name }}</h3>
        <p>{{ $p->tagline ?: \Illuminate\Support\Str::limit($p->summary, 110) }}</p>
        <span class="s-more">Learn more</span>
    </div>
</a>
