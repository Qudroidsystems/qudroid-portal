@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('modules.connect.notes._head', ['subtitle' => 'Note in ' . $conversation->titleFor($me)])

    <x-cb.card>
        <div class="d-flex flex-wrap gap-2 align-items-start mb-3">
            <div class="flex-grow-1 min-w-0">
                <h3 class="mb-1" id="ntTitle">@if($note->pinned)<i class="ri-pushpin-2-fill text-warning fs-5"></i> @endif{{ $note->title }}</h3>
                <div class="small text-muted" id="ntMeta">Last edited by {{ $note->editor?->name ?? 'someone' }} {{ $note->updated_at->diffForHumans() }} · created by {{ $note->creator?->name ?? 'someone' }}</div>
                <div class="small text-primary" id="ntEditing" aria-live="polite"></div>
            </div>
            <div class="d-flex gap-1 flex-wrap">
                @if($canEdit)
                    <a href="{{ route('connect.notes.edit', $note) }}" class="btn btn-sm btn-primary"><i class="ri-edit-line"></i> Edit</a>
                    <form method="POST" action="{{ route('connect.notes.pin', $note) }}" data-live>@csrf<button class="btn btn-sm btn-soft-secondary" title="{{ $note->pinned ? 'Unpin' : 'Pin to the top' }}"><i class="ri-pushpin-2-line"></i></button></form>
                @endif
                <a href="{{ route('connect.notes.history', $note) }}" class="btn btn-sm btn-soft-secondary" title="Version history"><i class="ri-history-line"></i></a>
                @if($canDelete)
                    <form method="POST" action="{{ route('connect.notes.destroy', $note) }}" onsubmit="return confirm('Delete this note and its history for everyone?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" title="Delete"><i class="ri-delete-bin-line"></i></button></form>
                @endif
            </div>
        </div>
        <div class="md-body" id="ntHtml">@if(trim((string) $note->body) !== ''){!! $note->html() !!}@else<p class="text-muted">This note is empty.@if($canEdit) <a href="{{ route('connect.notes.edit', $note) }}">Start writing</a>.@endif</p>@endif</div>
    </x-cb.card>
</div></div></div>
@endsection

@push('styles')
@include('modules.connect.notes._styles')
@endpush
@push('scripts')
<script>
(function () {
    let version = @json($note->version);
    const url = @json(route('connect.notes.state', $note));
    setInterval(() => {
        if (document.hidden) return;
        fetch(url + '?version=' + version, { headers: { 'Accept': 'application/json' } }).then(r => r.ok ? r.json() : null).then(s => {
            if (!s) return;
            document.getElementById('ntEditing').textContent = s.editing.length ? s.editing.join(', ') + (s.editing.length === 1 ? ' is' : ' are') + ' editing now…' : '';
            if (s.version !== version && s.html !== undefined) {
                version = s.version;
                document.getElementById('ntHtml').innerHTML = s.html || '<p class="text-muted">This note is empty.</p>';
                document.getElementById('ntTitle').textContent = s.title;
                document.getElementById('ntMeta').textContent = 'Last edited by ' + (s.by || 'someone') + ' ' + s.when;
                window.Live?.toast('Updated by ' + (s.by || 'someone') + '.');
            }
        }).catch(() => {});
    }, 6000);
})();
</script>
@endpush
