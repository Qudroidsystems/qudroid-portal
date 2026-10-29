@php($next = $c->cohorts->first())
<a class="s-card" href="{{ route('site.academy.course', $c->slug) }}">
    <div class="s-card-art">@include('site.blocks._art', ['image' => \App\Models\Website\Product::url($c->image), 'icon' => 'ri-book-open-line', 'color' => '#6366F1', 'class' => 'is-card', 'alt' => $c->title])</div>
    <div class="s-card-body">
        <span class="s-card-kicker">{{ collect([$c->level_label, $c->mode_label, $c->duration_weeks ? $c->duration_weeks . ' weeks' : null])->filter()->implode(' · ') }}</span>
        <h3>{{ $c->title }}</h3>
        @if($c->summary)<p>{{ \Illuminate\Support\Str::limit($c->summary, 120) }}</p>@endif
        <p style="color:var(--s-ink);font-weight:600">
            @if($next)
                {{ \App\Services\Website\Content::money($next->effectiveFee(), $c->currency) }} · starts {{ $next->starts_on?->format('j M Y') ?? 'soon' }}
            @else
                <span class="s-muted" style="font-weight:400">Registration opens soon</span>
            @endif
        </p>
        <span class="s-more">{{ $next ? 'Register' : 'Learn more' }}</span>
    </div>
</a>
