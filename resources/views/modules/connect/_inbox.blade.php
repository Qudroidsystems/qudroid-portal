@forelse($inbox as $c)
    @php
        $title = $c->titleFor($me);
        $other = $c->isDirect() ? $c->otherUser($me) : null;
        $last = $c->lastMessage;
    @endphp
    <a href="{{ route('connect.show', $c) }}" class="cx-item {{ $active && $active->id === $c->id ? 'active' : '' }} {{ $c->unread ? 'unread' : '' }}">
        <span class="cx-avatar {{ $c->type }}">
            @if($other)
                <img src="{{ $other->avatar_url }}" alt="">
            @else
                <i class="{{ $c->type === 'channel' ? 'ri-hashtag' : ($c->project_id ? 'ri-layout-column-line' : 'ri-group-line') }}"></i>
            @endif
        </span>
        <span class="cx-meta">
            <span class="d-flex justify-content-between gap-2">
                <span class="cx-title text-truncate">{{ $title }}</span>
                <span class="cx-time">{{ $last?->created_at?->isToday() ? $last->created_at->format('H:i') : $last?->created_at?->format('j M') }}</span>
            </span>
            <span class="d-flex justify-content-between gap-2">
                <span class="cx-preview text-truncate">@if($last && !$last->is_system && !$c->isDirect() && $last->user){{ $last->user_id === $me->id ? 'You' : $last->user->first_name }}: @elseif($last && $last->user_id === $me->id)You: @endif{{ $last?->preview(60) }}</span>
                @if($c->unread)<span class="badge rounded-pill {{ $c->muted ? 'bg-secondary' : 'bg-success' }}">{{ $c->unread }}</span>@elseif($c->muted)<i class="ri-notification-off-line text-muted"></i>@endif
            </span>
        </span>
    </a>
@empty
    <div class="text-muted small p-3">No conversations yet. Start one with the buttons above.</div>
@endforelse
