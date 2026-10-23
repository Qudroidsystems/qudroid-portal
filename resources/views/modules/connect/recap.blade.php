@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="($call->is_huddle ? 'Huddle' : ($call->kind === 'audio' ? 'Voice call' : 'Video call')) . ' recap'" icon="ri-file-list-3-line"
        :subtitle="$title . ' · ' . $call->started_at->format('j M Y, H:i') . ' · ' . $call->durationLabel()" :back="route('connect.show', $call->conversation_id)" back-label="Back to the chat">
        <x-slot:actions>
            @if($call->note)
                <a href="{{ route('connect.notes.show', $call->note) }}" class="cb-hero-btn"><i class="ri-sticky-note-line"></i>Open notes</a>
            @else
                <form method="POST" action="{{ route('connect.calls.note', $call) }}">@csrf<button class="cb-hero-btn"><i class="ri-sticky-note-add-line"></i>Write notes</button></form>
            @endif
            @if($call->conversation->meeting_id)
                <a href="{{ route('meetings.show', $call->conversation->meeting_id) }}" class="cb-hero-btn"><i class="ri-video-chat-line"></i>Meeting</a>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')
    @if($call->isLive())
        <div class="cb-banner info mb-3"><i class="ri-record-circle-line"></i> This call is still going. <a href="{{ route('connect.calls.show', $call) }}" target="_blank">Join it</a>.</div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Recordings" icon="ri-record-circle-line" :count="$recordings->count()">
                @forelse($recordings as $r)
                    <div class="rc-item">
                        @if($r->status === 'ready')
                            @if($r->isAudio())
                                <audio controls preload="metadata" src="{{ route('connect.recordings.show', $r) }}" class="w-100"></audio>
                            @else
                                <video controls preload="metadata" src="{{ route('connect.recordings.show', $r) }}" class="rc-video"></video>
                            @endif
                        @else
                            <div class="text-danger small"><i class="ri-record-circle-fill"></i> Recording now…</div>
                        @endif
                        <div class="d-flex flex-wrap gap-2 align-items-center small text-muted mt-2">
                            <span>By {{ $r->user?->name ?? 'someone' }} · {{ $r->started_at->format('H:i') }} · {{ $r->durationLabel() }} · {{ $r->sizeLabel() }}</span>
                            @if($r->status === 'ready')
                                <a href="{{ route('connect.recordings.show', [$r, 'download' => 1]) }}" class="ms-auto btn btn-sm btn-soft-secondary"><i class="ri-download-2-line"></i> Download</a>
                                @if($r->user_id === $me->id || $isAdmin)
                                    <form method="POST" action="{{ route('connect.recordings.destroy', $r) }}" data-confirm="Delete this recording for everyone?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" title="Delete"><i class="ri-delete-bin-line"></i></button></form>
                                @endif
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="empty-state"><i class="ri-record-circle-line"></i><h6>No recordings</h6><p class="text-muted small mb-0">Use the record button in a call to record it.</p></div>
                @endforelse
            </x-cb.card>

            <x-cb.card title="Transcript" icon="ri-chat-quote-line" :count="$lines->count()">
                @if($lines->isEmpty())
                    <div class="empty-state"><i class="ri-chat-quote-line"></i><h6>No transcript</h6><p class="text-muted small mb-0">Turn on the transcript in a call (the CC button) and what people say is written down here.</p></div>
                @else
                    <div class="d-flex gap-2 mb-2">
                        <input type="search" class="form-control form-control-sm" id="rcFilter" placeholder="Find in the transcript" aria-label="Find in the transcript">
                        <a href="{{ route('connect.calls.transcript', $call) }}" class="btn btn-sm btn-soft-secondary text-nowrap"><i class="ri-download-2-line"></i> .txt</a>
                    </div>
                    <div class="rc-lines" id="rcLines">
                        @foreach($lines as $l)
                            <div class="rc-line" data-text="{{ mb_strtolower(($l->user?->name ?? '') . ' ' . $l->text) }}">
                                <span class="rc-time">{{ $l->spoken_at->format('H:i:s') }}</span>
                                <div><strong>{{ $l->user?->name ?? 'Someone' }}</strong> {{ $l->text }}</div>
                            </div>
                        @endforeach
                    </div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Who came" icon="ri-team-line" :count="$people->unique('user_id')->count()" :flush="true">
                <ul class="list-group list-group-flush">
                    @foreach($people as $p)
                        <li class="list-group-item d-flex align-items-center gap-2">
                            <img src="{{ $p->user?->avatar_url }}" class="rounded-circle" width="26" height="26" alt="">
                            <span class="flex-grow-1">{{ $p->user?->name ?? 'Former user' }}</span>
                            <span class="small text-muted">{{ $p->joined_at->format('H:i') }}@if($p->left_at)–{{ $p->left_at->format('H:i') }}@endif</span>
                        </li>
                    @endforeach
                </ul>
            </x-cb.card>
            <x-cb.card title="Notes" icon="ri-sticky-note-line">
                @if($call->note)
                    <a href="{{ route('connect.notes.show', $call->note) }}" class="fw-semibold">{{ $call->note->title }}</a>
                    <div class="small text-muted">{{ $call->note->excerpt(140) }}</div>
                @else
                    <p class="small text-muted">Start shared notes from this call: when it was, who came, a place for decisions and action items{{ $lines->isNotEmpty() ? ', and the transcript' : '' }}. Everyone in the chat can edit them.</p>
                    <form method="POST" action="{{ route('connect.calls.note', $call) }}">@csrf<button class="btn btn-sm btn-primary"><i class="ri-sticky-note-add-line"></i> Write notes</button></form>
                @endif
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection

@push('styles')
<style>
    .rc-item + .rc-item { border-top: 1px solid var(--cb-border, #e5e7eb); margin-top: 14px; padding-top: 14px; }
    .rc-video { width: 100%; max-height: 60vh; background: #000; border-radius: 10px; }
    .rc-lines { max-height: 60vh; overflow: auto; }
    .rc-line { display: flex; gap: 10px; padding: 6px 0; border-bottom: 1px dashed var(--cb-border, #e5e7eb); }
    .rc-time { color: var(--cb-muted, #6b7280); font-size: 12px; font-variant-numeric: tabular-nums; padding-top: 2px; white-space: nowrap; }
</style>
@endpush
@push('scripts')
<script>
(function () {
    // Browser recordings carry no length; asking for the end makes the player work it out, so seeking works.
    document.querySelectorAll('.rc-item video, .rc-item audio').forEach(m => {
        const fix = () => {
            if (m.duration !== Infinity || m.dataset.fixed) return;
            m.dataset.fixed = 1;
            m.addEventListener('durationchange', function back() { if (m.duration === Infinity) return; m.removeEventListener('durationchange', back); m.currentTime = 0; });
            m.currentTime = 1e101;
        };
        m.readyState >= 1 ? fix() : m.addEventListener('loadedmetadata', fix);
    });
    const f = document.getElementById('rcFilter');
    if (!f) return;
    f.addEventListener('input', () => {
        const q = f.value.trim().toLowerCase();
        document.querySelectorAll('#rcLines .rc-line').forEach(l => l.classList.toggle('d-none', q !== '' && !l.dataset.text.includes(q)));
    });
})();
</script>
@endpush
