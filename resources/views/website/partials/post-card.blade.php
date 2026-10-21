@php $F = fn ($x) => \App\Services\Website\WebsiteContent::fileUrl($x); @endphp
<article class="card reveal d{{ $delay ?? 1 }}">
    <a class="thumb" href="{{ route('site.post', $p->slug) }}">
        @if($p->cover)<img src="{{ $F($p->cover) }}" alt="{{ $p->title }}" loading="lazy">
        @else<div style="position:absolute;inset:0;display:grid;place-items:center;background:linear-gradient(135deg,var(--secondary),var(--primary));color:rgba(255,255,255,.5);font-size:3rem"><i class="{{ $p->type === 'event' ? 'ri-calendar-event-line' : 'ri-newspaper-line' }}"></i></div>@endif
        <span class="tag">{{ $p->category ?: ['news' => 'News', 'event' => 'Event', 'story' => 'Student life'][$p->type] ?? 'News' }}</span>
    </a>
    <div class="body">
        <div class="meta">
            @if($p->type === 'event' && $p->event_date)
                <span><i class="ri-calendar-line"></i> {{ $p->event_date->format('d M Y') }}</span>
                @if($p->location)<span><i class="ri-map-pin-line"></i> {{ $p->location }}</span>@endif
            @else
                <span><i class="ri-calendar-line"></i> {{ optional($p->published_at ?? $p->created_at)->format('d M Y') }}</span>
            @endif
        </div>
        <h3><a href="{{ route('site.post', $p->slug) }}">{{ $p->title }}</a></h3>
        @if($p->excerpt)<p>{{ \Illuminate\Support\Str::limit($p->excerpt, 140) }}</p>@endif
        <a class="link-arrow" href="{{ route('site.post', $p->slug) }}">Read more <i class="ri-arrow-right-line"></i></a>
    </div>
</article>
