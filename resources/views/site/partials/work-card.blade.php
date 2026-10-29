<a class="s-card" href="{{ route('site.works.show', $w) }}">
    <div class="s-card-art">@include('site.blocks._art', ['image' => $w->imageUrl(), 'icon' => 'ri-briefcase-4-line', 'color' => $w->color, 'class' => 'is-card', 'alt' => $w->title])</div>
    <div class="s-card-body">
        <span class="s-card-kicker">{{ collect([$w->category, $w->client, $w->year])->filter()->implode(' · ') }}</span>
        <h3>{{ $w->title }}</h3>
        @if($w->summary)<p>{{ \Illuminate\Support\Str::limit($w->summary, 140) }}</p>@endif
        @if($w->tags)<div class="s-tags">@foreach(array_slice($w->tags, 0, 4) as $t)<span class="s-tag">{{ $t }}</span>@endforeach</div>@endif
        <span class="s-more">See the work</span>
    </div>
</a>
