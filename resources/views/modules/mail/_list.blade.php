{{-- The conversation list. Also returned on its own for live refreshes. --}}
@php
    $binView = in_array($view, ['trash', 'junk'], true);
    $from = $view;
@endphp
<div id="qmList" data-total="{{ $threads->total() }}">
    @if($threads->isEmpty())
        <div class="qm-empty">
            @if($q !== '')
                <i class="ri-search-line"></i>No messages matched your search.
            @elseif($syncing)
                <span class="spinner-border text-primary mb-2"></span><div>Loading your mail. This takes a moment the first time.</div>
            @else
                <i class="{{ ['inbox' => 'ri-inbox-line', 'starred' => 'ri-star-line', 'snoozed' => 'ri-time-line', 'sent' => 'ri-send-plane-line', 'junk' => 'ri-spam-2-line', 'trash' => 'ri-delete-bin-6-line'][$view] ?? 'ri-mail-line' }}"></i>
                {{ ['inbox' => 'Your inbox is empty. Nice.', 'starred' => 'No starred messages. Stars let you give messages a special status to make them easier to find.', 'snoozed' => 'Nothing snoozed. Snooze mail to come back to it later.', 'sent' => 'No sent messages yet.', 'junk' => 'Hooray, no spam here!', 'trash' => 'No conversations in the bin.'][$view] ?? 'Nothing here.' }}
            @endif
        </div>
    @else
        <ul class="qm-list" role="list">
            @foreach($threads as $t)
                <li class="qm-row {{ $t->unread ? 'unread' : '' }}" data-key="{{ $t->key }}" data-href="{{ route('mail.thread', [$account, $t->key, 'from' => $from]) }}" tabindex="-1">
                    <input type="checkbox" class="form-check-input m-0" aria-label="Select conversation" data-sel>
                    <button type="button" class="qm-star {{ $t->starred ? 'on' : '' }}" data-star aria-label="{{ $t->starred ? 'Starred' : 'Not starred' }}"><i class="{{ $t->starred ? 'ri-star-fill' : 'ri-star-line' }}"></i></button>
                    <div class="who">
                        @if($view === 'sent')<small>To: </small>{{ $t->to ?: '(no recipients)' }}@else{{ $t->people ?: '(unknown sender)' }}@endif
                        @if($t->count > 1)<small class="ms-1">{{ $t->count }}</small>@endif
                    </div>
                    <div class="what">
                        {{ $t->subject }} <span class="snip">— {{ $t->snippet }}</span>
                    </div>
                    <div class="when">
                        @if($t->files)<i class="ri-attachment-2 me-1 fw-normal" aria-label="Has attachments"></i>@endif
                        @if($view === 'snoozed' && $t->snoozed_until)<span class="text-warning">{{ \Illuminate\Support\Carbon::parse($t->snoozed_until)->format('j M, g:i A') }}</span>
                        @else @include('modules.mail._when', ['at' => $t->at]) @endif
                    </div>
                    <div class="row-acts">
                        @if($binView)
                            <button type="button" data-act="inbox" title="Move to inbox" aria-label="Move to inbox"><i class="ri-inbox-unarchive-line"></i></button>
                            <button type="button" data-act="delete" title="Delete for good" aria-label="Delete for good"><i class="ri-delete-bin-2-line"></i></button>
                        @else
                            @if($view !== 'archive')<button type="button" data-act="archive" title="Archive (e)" aria-label="Archive"><i class="ri-archive-line"></i></button>@endif
                            <button type="button" data-act="trash" title="Delete (#)" aria-label="Delete"><i class="ri-delete-bin-6-line"></i></button>
                            <button type="button" data-act="{{ $t->unread ? 'read' : 'unread' }}" title="Mark as {{ $t->unread ? 'read' : 'unread' }}" aria-label="Mark as {{ $t->unread ? 'read' : 'unread' }}"><i class="{{ $t->unread ? 'ri-mail-open-line' : 'ri-mail-unread-line' }}"></i></button>
                            <button type="button" data-act="snooze" title="Snooze" aria-label="Snooze"><i class="ri-time-line"></i></button>
                        @endif
                    </div>
                </li>
            @endforeach
        </ul>
    @endif
</div>
