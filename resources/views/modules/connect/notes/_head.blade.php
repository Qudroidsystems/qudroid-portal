{{-- Header and tabs shared by a chat's Notes and Files pages. --}}
<x-cb.hero :title="$conversation->titleFor($me)" icon="ri-sticky-note-line" :subtitle="$subtitle ?? 'Shared notes and files for everyone in this chat.'" :back="route('connect.show', $conversation)" back-label="Back to the chat" />
@include('partials.flash')
<div class="d-flex flex-wrap gap-2 mb-3">
    <a href="{{ route('connect.show', $conversation) }}" class="btn btn-sm btn-soft-secondary"><i class="ri-chat-3-line"></i> Chat</a>
    <a href="{{ route('connect.notes.index', $conversation) }}" class="btn btn-sm {{ ($tab ?? 'notes') === 'notes' ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="ri-sticky-note-line"></i> Notes</a>
    <a href="{{ route('connect.notes.index', [$conversation, 'tab' => 'files']) }}" class="btn btn-sm {{ ($tab ?? '') === 'files' ? 'btn-primary' : 'btn-soft-secondary' }}"><i class="ri-folder-3-line"></i> Files</a>
</div>
