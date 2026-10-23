{{-- resources/views/notifications/index.blade.php --}}
@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <x-cb.hero title="Notifications" icon="ri-notification-3-line" subtitle="Approvals, messages, meetings and alerts. Answer most of them right here.">
        <x-slot:actions>
            @if($unread)
                <form method="POST" action="{{ route('notifications.read-all') }}">@csrf
                    <button class="cb-hero-btn"><i class="ri-check-double-line"></i>Mark all as read</button></form>
            @endif
            <form method="POST" action="{{ route('notifications.clear-read') }}" onsubmit="return confirm('Remove all the notifications you have read?')">@csrf
                <button class="cb-hero-btn"><i class="ri-delete-bin-6-line"></i>Clear read</button></form>
        </x-slot:actions>
    </x-cb.hero>

    @if(session('success'))<div class="cb-banner success"><i class="ri-check-line"></i><div>{{ session('success') }}</div></div>@endif
    @if($errors->any())<div class="cb-banner danger"><i class="ri-error-warning-line"></i><div>{{ $errors->first() }}</div></div>@endif

    <div class="row g-4">
        <div class="col-xl-8">
            <div class="d-flex flex-wrap gap-2 align-items-center mb-3">
                <div class="term-chips" role="tablist" aria-label="Show">
                    @foreach($filters as $k => $label)
                        <a class="term-chip {{ $filter === $k ? 'active' : '' }}" href="{{ route('notifications.index', $k === 'all' ? [] : ['filter' => $k]) }}" role="tab" aria-selected="{{ $filter === $k ? 'true' : 'false' }}">{{ $label }}@if($k === 'unread' && $unread) ({{ $unread }})@endif</a>
                    @endforeach
                </div>
                <select class="form-select form-select-sm w-auto" aria-label="Kind" onchange="location.href=this.value">
                    <option value="{{ route('notifications.index') }}">Every kind</option>
                    @foreach($groups as $key => [$label])
                        <option value="{{ route('notifications.index', ['filter' => $key]) }}" @selected($filter === $key)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>

            <div id="np-list" class="np-list">
                @forelse($items->groupBy(fn ($i) => \Illuminate\Support\Carbon::parse($i['created_at'])->isToday() ? 'Today' : (\Illuminate\Support\Carbon::parse($i['created_at'])->isYesterday() ? 'Yesterday' : 'Earlier')) as $section => $rows)
                    <div class="nc-section"><span>{{ $section }}</span></div>
                    @foreach($rows as $n)
                        <div data-n="{{ json_encode($n) }}"></div>
                    @endforeach
                @empty
                    <div class="cb-card"><div class="empty-state"><i class="ri-notification-off-line"></i><h6>No notifications</h6><p>You're all caught up.</p></div></div>
                @endforelse
            </div>
            <div class="mt-3">{{ $page->links() }}</div>
        </div>

        <div class="col-xl-4" id="settings">
            <form method="POST" action="{{ route('notifications.settings') }}">
                @csrf
                <h2 class="np-h">Notify me about</h2>
                <div class="np-group">
                    @foreach($settings['groups'] as $g)
                        <label class="np-row" for="ng-{{ $g['key'] }}">
                            <span class="nc-app" style="background:{{ $g['color'] }}"><i class="{{ $g['icon'] }}"></i></span>
                            <span class="flex-grow-1">{{ $g['label'] }}</span>
                            <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="on[]" value="{{ $g['key'] }}" id="ng-{{ $g['key'] }}" @checked($g['on'])></span>
                        </label>
                    @endforeach
                </div>
                <p class="np-foot">Switched-off kinds don't reach your bell or phone. Announcements and system messages always do. Mentions count as Messages.</p>

                <h2 class="np-h">Quiet hours</h2>
                <div class="np-group">
                    <label class="np-row" for="ng-quiet">
                        <span class="nc-app" style="background:#5856d6"><i class="ri-moon-line"></i></span>
                        <span class="flex-grow-1">Pause banners, sounds and phone alerts</span>
                        <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="quiet" value="1" id="ng-quiet" @checked($settings['quiet_from'])></span>
                    </label>
                    <div class="np-row" id="ng-quiet-times" @if(!$settings['quiet_from']) hidden @endif>
                        <span class="flex-grow-1">From</span>
                        <input type="time" name="quiet_from" class="form-control form-control-sm w-auto" value="{{ $settings['quiet_from'] ?? '20:00' }}" aria-label="Quiet from">
                        <span class="px-1">to</span>
                        <input type="time" name="quiet_to" class="form-control form-control-sm w-auto" value="{{ $settings['quiet_to'] ?? '07:00' }}" aria-label="Quiet until">
                    </div>
                </div>
                <p class="np-foot">Calls, approvals and meeting reminders still come through. Everything else waits here for you.</p>

                <h2 class="np-h">Daily summary</h2>
                <div class="np-group">
                    <label class="np-row" for="ng-digest">
                        <span class="nc-app" style="background:#007aff"><i class="ri-mail-line"></i></span>
                        <span class="flex-grow-1">E-mail me what I missed, each morning</span>
                        <span class="form-check form-switch m-0"><input class="form-check-input" type="checkbox" role="switch" name="digest" value="1" id="ng-digest" @checked($settings['digest'])></span>
                    </label>
                    <div class="np-row">
                        <span class="nc-app" style="background:#ff9500"><i class="ri-volume-up-line"></i></span>
                        <span class="flex-grow-1">Sounds on this device</span>
                        <button type="button" class="btn btn-sm btn-light" id="ngSound">…</button>
                    </div>
                </div>
                <p class="np-foot">Only sent when something is still unread.</p>
                <button class="btn btn-primary w-100 mt-1">Save</button>
            </form>
        </div>
    </div>
</div>
</div>
</div>
<style>
.np-list .nc-item { margin-bottom: 10px; }
.np-list .nc-section { margin: 18px 4px 8px; font-size: 15px; color: var(--qd-label, #1d1d1f); }
.np-list .nc-section:first-child { margin-top: 0; }
.np-h { font-size: 13px; font-weight: 500; color: var(--qd-label-2, #6e6e73); margin: 0 14px 6px; text-transform: uppercase; letter-spacing: .02em; }
.np-group { background: var(--qd-card, #fff); border-radius: 12px; overflow: hidden; box-shadow: 0 0 0 .5px var(--qd-sep, rgba(0,0,0,.08)); }
.np-row { display: flex; align-items: center; gap: 12px; padding: 9px 14px; margin: 0; cursor: pointer; font-size: 14px; position: relative; }
.np-row + .np-row::before { content: ''; position: absolute; top: 0; left: 46px; right: 0; border-top: .5px solid var(--qd-sep, rgba(0,0,0,.1)); }
.np-row .nc-app { width: 26px; height: 26px; border-radius: 7px; font-size: 15px; }
.np-foot { font-size: 12px; color: var(--qd-label-2, #6e6e73); margin: 6px 14px 22px; }
</style>
<script>
window.addEventListener('load', function () {
    // Draw each notification with the same card as the notification centre.
    document.querySelectorAll('#np-list [data-n]').forEach(el => { el.outerHTML = QNotify.card(JSON.parse(el.dataset.n)); });
    document.getElementById('np-list').addEventListener('click', QNotify.onClick);
    const q = document.getElementById('ng-quiet');
    q.addEventListener('change', () => document.getElementById('ng-quiet-times').hidden = !q.checked);
    const b = document.getElementById('ngSound');
    if (!b || !window.QSound) return;
    const show = () => b.textContent = QSound.on() ? 'On' : 'Off';
    b.addEventListener('click', () => { QSound.setOn(!QSound.on()); show(); if (QSound.on()) QSound.notify(); });
    show();
});
</script>
@endsection
