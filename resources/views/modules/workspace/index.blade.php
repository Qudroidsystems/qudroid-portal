@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Workspace" icon="ri-apps-2-line"
        subtitle="Good {{ now()->hour < 12 ? 'morning' : (now()->hour < 17 ? 'afternoon' : 'evening') }}, {{ $me->first_name }}. Your chats, calls, meetings and files in one place." />

    <div class="row g-2 mb-3">
        @foreach([
            ['Chat', 'ri-chat-3-line', route('connect.index'), '#405189'],
            ['Calls', 'ri-vidicon-line', route('connect.calls.index'), '#0ab39c'],
            ['Drive', 'ri-folder-3-line', route('drive.index'), '#f7b84b'],
            ['Calendar', 'ri-calendar-event-line', Route::has('meetings.calendar') && ($me->can('View meetings') || $me->can('Manage meetings')) ? route('meetings.calendar') : null, '#299cdb'],
        ] as [$label, $icon, $url, $color])
            @if($url)
                <div class="col-6 col-md-3">
                    <a href="{{ $url }}" class="ws-tile"><span class="ws-icon" style="background: {{ $color }}1f; color: {{ $color }}"><i class="{{ $icon }}"></i></span><span class="fw-semibold">{{ $label }}</span></a>
                </div>
            @endif
        @endforeach
    </div>

    @if($calls->isNotEmpty())
        @foreach($calls as $call)
            <div class="cb-banner success d-flex align-items-center gap-2 flex-wrap">
                <i class="{{ $call->kind === 'audio' ? 'ri-phone-line' : 'ri-vidicon-line' }}"></i>
                <span class="flex-grow-1">A call is going on in <strong>{{ $call->conversation->titleFor($me) }}</strong>, started by {{ $call->started_by === $me->id ? 'you' : $call->starter?->name }} at {{ $call->started_at->format('H:i') }}.</span>
                <a href="{{ route('connect.calls.show', $call) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">Join</a>
            </div>
        @endforeach
    @endif

    <div class="row g-3">
        <div class="col-lg-7">
            <x-cb.card title="Needs a reply" icon="ri-chat-unread-line" :count="$unread->count()" :flush="true">
                <x-slot:tools><a href="{{ route('connect.index') }}" class="btn btn-sm btn-soft-primary">Open chat</a></x-slot:tools>
                @forelse($unread as $c)
                    @php $last = $c->lastMessage; @endphp
                    <a href="{{ route('connect.show', $c) }}" class="ws-row">
                        <span class="fw-semibold text-truncate">{{ $c->titleFor($me) }}</span>
                        <span class="text-muted small text-truncate flex-grow-1">@if($last && $last->user && !$c->isDirect()){{ $last->user->first_name }}: @endif{{ $last?->preview(80) }}</span>
                        <span class="badge rounded-pill {{ $c->muted ? 'bg-secondary' : 'bg-success' }}">{{ $c->unread }}</span>
                    </a>
                @empty
                    <div class="text-muted small p-3"><i class="ri-check-double-line text-success me-1"></i>You're all caught up.</div>
                    @foreach($recentChats as $c)
                        <a href="{{ route('connect.show', $c) }}" class="ws-row">
                            <span class="fw-semibold text-truncate">{{ $c->titleFor($me) }}</span>
                            <span class="text-muted small text-truncate flex-grow-1">{{ $c->lastMessage?->preview(80) }}</span>
                            <span class="text-muted small">{{ $c->lastMessage?->created_at?->diffForHumans(short: true) }}</span>
                        </a>
                    @endforeach
                @endforelse
            </x-cb.card>

            <x-cb.card title="Recent files" icon="ri-folder-3-line" :flush="true">
                <x-slot:tools><a href="{{ route('drive.index') }}" class="btn btn-sm btn-soft-primary">Open Drive</a></x-slot:tools>
                @forelse($files as $f)
                    <a href="{{ $drive->url($f) }}" class="ws-row">
                        <i class="{{ $f->icon() }} fs-5 text-muted"></i>
                        <span class="fw-semibold text-truncate flex-grow-1">{{ $f->name }}</span>
                        <span class="text-muted small d-none d-sm-inline">{{ \App\Models\Drive\File::humanSize($f->size) }}</span>
                        <span class="text-muted small">{{ $f->updated_at->diffForHumans(short: true) }}</span>
                    </a>
                @empty
                    <div class="text-muted small p-3">No files yet. <a href="{{ route('drive.index') }}">Upload some to Drive.</a></div>
                @endforelse
            </x-cb.card>
        </div>

        <div class="col-lg-5">
            @if(Route::has('meetings.index') && ($me->can('View meetings') || $me->can('Manage meetings')))
                <x-cb.card title="Coming up" icon="ri-calendar-event-line" :flush="true">
                    <x-slot:tools>@can('Manage meetings')<a href="{{ route('meetings.create') }}" class="btn btn-sm btn-soft-primary">Schedule</a>@endcan</x-slot:tools>
                    @forelse($meetings as $m)
                        <a href="{{ route('meetings.show', $m) }}" class="ws-row">
                            <span class="ws-date"><span>{{ $m->starts_at->format('M') }}</span><strong>{{ $m->starts_at->format('j') }}</strong></span>
                            <span class="flex-grow-1 text-truncate">
                                <span class="fw-semibold d-block text-truncate">{{ $m->title }}</span>
                                <span class="small text-muted">{{ $m->starts_at->isToday() ? 'Today' : $m->starts_at->format('D') }} {{ $m->starts_at->format('H:i') }}–{{ $m->ends_at->format('H:i') }}@if($m->whereLabel()) · {{ $m->whereLabel() }}@endif</span>
                            </span>
                            @if($m->starts_at->lte(now()) && $m->ends_at->gte(now()))<span class="badge bg-success">Now</span>@endif
                        </a>
                    @empty
                        <div class="text-muted small p-3">Nothing on your calendar.</div>
                    @endforelse
                </x-cb.card>
            @endif

            <x-cb.card title="Online now" icon="ri-user-voice-line" :count="$online->count()">
                @php $wsPresence = app(\App\Services\Presence\Presence::class)->prime($online->pluck('id')); @endphp
                @forelse($online as $u)
                    @php $pr = $wsPresence->of($u); @endphp
                    <a href="{{ route('connect.direct', $u) }}" class="ws-person" title="{{ $u->name }}: {{ $wsPresence->line($u) }}"><span class="position-relative d-inline-block"><img src="{{ $u->avatar_url }}" alt=""><span class="ws-pdot" style="background: {{ $pr['color'] }}"></span></span><span class="text-truncate">{{ $u->first_name }}@if($pr['emoji']) {{ $pr['emoji'] }}@endif</span></a>
                @empty
                    <div class="text-muted small">Nobody else is online right now.</div>
                @endforelse
            </x-cb.card>
        </div>
    </div>
</div></div></div>

<style>
    .ws-tile { display:flex; align-items:center; gap:.75rem; padding:.85rem 1rem; border-radius:.6rem; background:var(--vz-card-bg, #fff); border:1px solid var(--vz-border-color, #e9ebec); color:inherit; transition:box-shadow .15s; }
    .ws-tile:hover { box-shadow:0 4px 14px rgba(0,0,0,.06); color:inherit; }
    .ws-icon { width:40px; height:40px; border-radius:.5rem; display:inline-flex; align-items:center; justify-content:center; font-size:1.3rem; }
    .ws-row { display:flex; align-items:center; gap:.75rem; padding:.6rem 1rem; border-bottom:1px solid var(--vz-border-color, #f0f0f0); color:inherit; min-width:0; }
    .ws-row:last-child { border-bottom:0; }
    .ws-row:hover { background:var(--vz-light, #f7f8fa); color:inherit; }
    .ws-row > .fw-semibold.text-truncate:first-child { max-width:40%; }
    .ws-date { width:44px; text-align:center; line-height:1.1; flex-shrink:0; }
    .ws-date span { display:block; font-size:.7rem; text-transform:uppercase; color:var(--vz-secondary-color, #878a99); }
    .ws-date strong { font-size:1.2rem; }
    .ws-person { display:inline-flex; color:inherit; text-decoration:none; flex-direction:column; align-items:center; gap:.25rem; width:64px; margin:0 .25rem .5rem 0; border:0; background:none; font-size:.75rem; position:relative; }
    .ws-pdot { position:absolute; right:-1px; bottom:-1px; width:10px; height:10px; border-radius:50%; border:2px solid var(--cb-surface); }
    .ws-person img { width:40px; height:40px; border-radius:50%; box-shadow:0 0 0 2px #0ab39c; }
    .ws-person span { max-width:64px; }
</style>
@endsection
