@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('modules.connect.notes._head')

    @if($tab === 'files')
        <x-cb.card title="Files shared here" icon="ri-folder-3-line" :count="$files->count()" :flush="true">
            @if($files->isEmpty())
                <div class="empty-state py-5"><i class="ri-folder-open-line"></i><h6>No files yet</h6><p class="small text-muted mb-0">Files and photos sent in the chat appear here.</p></div>
            @else
                <div class="table-responsive"><table class="cb-table stack">
                    <thead><tr><th>Name</th><th>From</th><th>When</th><th class="num">Size</th><th></th></tr></thead>
                    <tbody>
                    @foreach($files as $f)
                        <tr>
                            <td data-label="Name"><i class="{{ $f->isImage() ? 'ri-image-line' : 'ri-file-3-line' }}"></i> <a href="{{ route('connect.messages.file', $f) }}" target="_blank">{{ $f->attachment_name }}</a></td>
                            <td data-label="From">{{ $f->user?->name ?? '—' }}</td>
                            <td data-label="When">{{ $f->created_at->format('j M Y, H:i') }}</td>
                            <td data-label="Size" class="num">{{ \Illuminate\Support\Number::fileSize($f->attachment_size ?? 0) }}</td>
                            <td><a href="{{ route('connect.show', [$conversation, 'around' => $f->thread_id ?: $f->id]) }}#m{{ $f->id }}" class="btn btn-sm btn-ghost-secondary" title="Show in the chat"><i class="ri-chat-3-line"></i></a>
                                <a href="{{ route('connect.messages.file', [$f, 'download' => 1]) }}" class="btn btn-sm btn-ghost-secondary" title="Download"><i class="ri-download-2-line"></i></a></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
            @endif
        </x-cb.card>
    @else
        <div class="row g-3">
            <div class="col-xl-8">
                @forelse($notes as $n)
                    <a href="{{ route('connect.notes.show', $n) }}" class="cb-card d-block p-3 mb-2 text-decoration-none nt-card">
                        <div class="d-flex justify-content-between gap-2">
                            <h6 class="mb-1 text-truncate">@if($n->pinned)<i class="ri-pushpin-2-fill text-warning"></i> @endif{{ $n->title }}</h6>
                            <span class="small text-muted text-nowrap">{{ $n->updated_at->diffForHumans() }}</span>
                        </div>
                        <div class="small text-muted text-truncate">{{ $n->excerpt() ?: 'Empty note' }}</div>
                        <div class="small text-muted mt-1">Last edited by {{ $n->editor?->name ?? 'someone' }}</div>
                    </a>
                @empty
                    <x-cb.card><div class="empty-state py-5"><i class="ri-sticky-note-line"></i><h6>No notes yet</h6><p class="small text-muted mb-0">Keep agendas, decisions, how-tos and checklists here, where everyone in the chat can edit them.</p></div></x-cb.card>
                @endforelse
            </div>
            <div class="col-xl-4">
                @if($canWrite)
                    <x-cb.card title="New note" icon="ri-add-line">
                        <form method="POST" action="{{ route('connect.notes.store', $conversation) }}">
                            @csrf
                            <input name="title" class="form-control mb-2" maxlength="150" required placeholder="Title, e.g. Meeting notes, Onboarding checklist">
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach(['Meeting notes' => "## Agenda\n- Topic\n\n## Decisions\n- Decision\n\n## Action items\n- [ ] Who does what, by when", 'Checklist' => "- [ ] First thing\n- [ ] Second thing\n- [ ] Third thing", 'How-to' => "## Steps\n1. First step\n2. Second step\n3. Third step\n\n## Tips\n- Tip"] as $label => $tpl)
                                    <button type="button" class="btn btn-sm btn-soft-secondary nt-tpl" data-tpl="{{ $tpl }}" data-title="{{ $label }}">{{ $label }}</button>
                                @endforeach
                            </div>
                            <input type="hidden" name="body" id="ntBody">
                            <button class="btn btn-primary w-100">Create and edit</button>
                        </form>
                    </x-cb.card>
                @endif
            </div>
        </div>
    @endif
</div></div></div>
@endsection

@push('styles')
<style>.nt-card { color: var(--cb-text); } .nt-card:hover { border-color: var(--cb-teal); }</style>
@endpush
@push('scripts')
<script>
document.querySelectorAll('.nt-tpl').forEach(b => b.addEventListener('click', () => {
    const f = b.closest('form');
    if (!f.title.value.trim()) f.title.value = b.dataset.title;
    document.getElementById('ntBody').value = b.dataset.tpl;
    f.querySelectorAll('.nt-tpl').forEach(x => x.classList.toggle('btn-soft-primary', x === b));
}));
</script>
@endpush
