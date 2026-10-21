<div class="date"><div><b>{{ $e->start->format('d') }}</b><small>{{ $e->start->format('M') }}</small></div></div>
<div>
    <h4>{{ $e->title }}</h4>
    <div class="where">{{ $e->start->format('l') }}@if($e->end && !$e->end->isSameDay($e->start)) – {{ $e->end->format('l d M') }}@endif{{ $e->time ? ' · ' . $e->time : '' }}{{ $e->location ? ' · ' . $e->location : '' }}</div>
    @if($e->desc)<div style="font-size:.95rem;margin-top:4px">{{ \Illuminate\Support\Str::limit(strip_tags($e->desc), 160) }}</div>@endif
</div>
