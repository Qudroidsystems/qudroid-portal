@php
    $mine = $m->user_id === $me->id;
    $saved = $saved ?? [];
    $inThread = $inThread ?? false;
    $isSaved = isset($saved[$m->id]);
@endphp
@if($m->is_system)
    <div class="cx-system" data-id="{{ $m->id }}">{{ $m->body }} · {{ $m->created_at->format('j M H:i') }}</div>
@else
    <div class="cx-msg {{ $mine ? 'mine' : '' }} {{ !$mine && $m->mentionsUser($me) ? 'mentioned' : '' }}" @unless($inThread) id="m{{ $m->id }}" @endunless data-id="{{ $m->id }}" data-user="{{ $m->user_id }}" data-body="{{ $m->body }}">
        @unless($mine)<img class="cx-msg-avatar" src="{{ $m->user?->avatar_url }}" alt="" title="{{ $m->user?->name }}">@endunless
        <div class="cx-bubble-wrap">
            @unless($mine || $conversation->isDirect())<div class="cx-author">{{ $m->user?->name ?? 'Former user' }}</div>@endunless
            <div class="cx-bubble {{ $m->isDeleted() ? 'deleted' : '' }}">
                @if($m->pinned_at && !$m->isDeleted())<div class="cx-pinned-tag"><i class="ri-pushpin-2-fill"></i> Pinned</div>@endif
                @if($m->isDeleted())
                    <i class="ri-delete-bin-line"></i> Message deleted
                @else
                    @if($m->replyTo)
                        <div class="cx-quote"><strong>{{ $m->replyTo->user?->name ?? 'Someone' }}</strong> {{ $m->replyTo->preview(90) }}</div>
                    @endif
                    @if($m->attachment_path)
                        @if($m->isImage())
                            <a href="{{ route('connect.messages.file', $m) }}" target="_blank"><img class="cx-image" src="{{ route('connect.messages.file', $m) }}" alt="{{ $m->attachment_name }}" loading="lazy"></a>
                        @else
                            <a class="cx-file" href="{{ route('connect.messages.file', [$m, 'download' => 1]) }}"><i class="ri-file-3-line"></i> <span>{{ $m->attachment_name }}<small class="d-block">{{ \Illuminate\Support\Number::fileSize($m->attachment_size ?? 0) }}</small></span></a>
                        @endif
                    @endif
                    @if($m->body)<div class="cx-text">{!! $m->html($me) !!}</div>@endif
                @endif
                <span class="cx-stamp">@if($isSaved)<i class="ri-bookmark-fill" title="Saved"></i> @endif{{ $m->created_at->format('H:i') }}@if($m->edited_at && !$m->isDeleted()) · edited @endif</span>
            </div>
            @if($m->reactions->isNotEmpty())
                <div class="cx-reactions">
                    @foreach($m->reactions->groupBy('emoji') as $emoji => $rs)
                        <button type="button" class="cx-react {{ $rs->contains('user_id', $me->id) ? 'me' : '' }}" data-emoji="{{ $emoji }}">{{ $emoji }} {{ $rs->count() }}</button>
                    @endforeach
                </div>
            @endif
            @if(!$m->thread_id && !$inThread && $m->reply_count > 0)
                <button type="button" class="cx-thread-link cx-thread-open"><i class="ri-chat-thread-line"></i> {{ $m->reply_count }} {{ \Illuminate\Support\Str::plural('reply', $m->reply_count) }}<span class="text-muted"> · {{ $m->last_reply_at?->diffForHumans(short: true) }}</span></button>
            @endif
        </div>
        @unless($m->isDeleted())
            <div class="cx-actions">
                <div class="dropdown">
                    <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" aria-label="Message actions"><i class="ri-more-2-fill"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <div class="px-2 pb-1 d-flex gap-1">@foreach(\App\Models\Connect\Reaction::ALLOWED as $e)<button type="button" class="btn btn-sm btn-light cx-react" data-emoji="{{ $e }}">{{ $e }}</button>@endforeach</div>
                        <button type="button" class="dropdown-item cx-reply" data-preview="{{ $m->preview(60) }}" data-name="{{ $m->user?->name }}"><i class="ri-reply-line"></i> Reply</button>
                        @if(!$m->thread_id && !$inThread)<button type="button" class="dropdown-item cx-thread-open"><i class="ri-chat-thread-line"></i> Reply in thread</button>@endif
                        <button type="button" class="dropdown-item cx-pin"><i class="ri-pushpin-2-line"></i> {{ $m->pinned_at ? 'Unpin' : 'Pin' }}</button>
                        <button type="button" class="dropdown-item cx-save"><i class="{{ $isSaved ? 'ri-bookmark-fill' : 'ri-bookmark-line' }}"></i> {{ $isSaved ? 'Remove from saved' : 'Save' }}</button>
                        @if($mine)<button type="button" class="dropdown-item cx-edit"><i class="ri-edit-line"></i> Edit</button>@endif
                        @if($mine || ($isAdmin ?? false))<button type="button" class="dropdown-item text-danger cx-delete"><i class="ri-delete-bin-line"></i> Delete</button>@endif
                    </div>
                </div>
            </div>
        @endunless
    </div>
@endif
