{{-- resources/views/notifications/index.blade.php --}}
@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <x-cb.hero title="Notifications" icon="ri-notification-3-line" subtitle="Announcements, approvals, tasks, messages and alerts for your account.">
        <x-slot:actions>
            @if($unread)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                    <button class="cb-hero-btn"><i class="ri-check-double-line"></i>Mark all as read</button></form>
            @endif
            <form method="POST" action="{{ route('notifications.clear-read') }}" onsubmit="return confirm('Remove all the notifications you have read?')">@csrf
                <button class="cb-hero-btn"><i class="ri-delete-bin-6-line"></i>Clear read</button></form>
        </x-slot:actions>
    </x-cb.hero>

    @if(session('success'))<div class="cb-banner info"><i class="ri-information-line"></i><div>{{ session('success') }}</div></div>@endif

    <div class="row g-3">
    <div class="col-xl-8">
    <x-cb.card title="Inbox" icon="ri-inbox-line" :count="$notifications->total()" :flush="true">
        <div class="cb-toolbar flex-wrap gap-2">
            <div class="term-chips flex-wrap">
                <a class="term-chip {{ request('filter') !== 'unread' && !$kind ? 'active' : '' }}" href="{{ route('notifications.index') }}">All</a>
                <a class="term-chip {{ request('filter') === 'unread' ? 'active' : '' }}" href="{{ route('notifications.index', array_filter(['filter' => 'unread', 'kind' => $kind])) }}">Unread ({{ $unread }})</a>
                @foreach($groups as $key => [$label, $icon])
                    <a class="term-chip {{ $kind === $key ? 'active' : '' }}" href="{{ route('notifications.index', array_filter(['kind' => $key, 'filter' => request('filter')])) }}"><i class="{{ $icon }} me-1"></i>{{ $label }}</a>
                @endforeach
            </div>
        </div>
        @if($notifications->isEmpty())
            <div class="empty-state"><i class="ri-notification-off-line"></i><h6>No notifications</h6><p>You're all caught up.</p></div>
        @else
            <div class="list-group list-group-flush">
                @foreach($notifications as $n)
                    <a href="{{ route('notifications.open', $n->id) }}" class="list-group-item list-group-item-action d-flex gap-3 py-3 {{ $n->read_at ? '' : 'pn-unread' }}">
                        <span class="pn-icon"><i class="{{ $n->data['icon'] ?? 'ri-notification-3-line' }}"></i></span>
                        <span class="flex-grow-1">
                            <span class="d-flex justify-content-between gap-2"><strong>{{ $n->data['title'] ?? 'Notification' }}</strong><small class="text-muted text-nowrap">{{ $n->created_at->diffForHumans() }}</small></span>
                            <span class="d-block small text-muted" style="white-space:pre-line">{{ $n->data['body'] ?? '' }}</span>
                        </span>
                    </a>
                @endforeach
            </div>
            <div class="p-3">{{ $notifications->links() }}</div>
        @endif
    </x-cb.card>
    </div>
    <div class="col-xl-4">
        <x-cb.card title="What to tell me about" icon="ri-settings-3-line">
            <form method="POST" action="{{ route('notifications.settings') }}">
                @csrf
                @foreach($groups as $key => [$label, $icon])
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="on[]" value="{{ $key }}" id="ng-{{ $key }}" @checked(!in_array($key, $muted, true))>
                        <label class="form-check-label" for="ng-{{ $key }}"><i class="{{ $icon }} me-1 text-muted"></i>{{ $label }}</label>
                    </div>
                @endforeach
                <div class="small text-muted mb-3">Switched-off kinds don't reach your bell or phone. Announcements and system messages always do. Mentions of you in chat count as Messages.</div>
                <button class="btn btn-primary btn-sm">Save</button>
            </form>
            <hr>
            <div class="d-flex justify-content-between align-items-center">
                <span class="small">Sounds on this device</span>
                <button type="button" class="btn btn-sm btn-soft-secondary" id="ngSound">…</button>
            </div>
            <div class="small text-muted mt-1">Ringing for calls, a chime for notifications and a tone for messages.</div>
        </x-cb.card>
    </div>
    </div>
</div>
</div>
</div>
<script>
window.addEventListener('load', function () {
    const b = document.getElementById('ngSound');
    if (!b || !window.QSound) return;
    const show = () => b.textContent = QSound.on() ? 'On: turn off' : 'Off: turn on';
    b.addEventListener('click', () => { QSound.setOn(!QSound.on()); show(); if (QSound.on()) QSound.notify(); });
    show();
});
</script>
<style>
.pn-icon { width: 38px; height: 38px; border-radius: 10px; background: rgba(13,148,136,.1); color: var(--cb-teal); display: inline-flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.pn-unread { background: rgba(13,148,136,.05); } .pn-unread strong::before { content: '● '; color: var(--cb-teal); }
</style>
@endsection
