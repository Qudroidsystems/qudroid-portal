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
            @unless($mine || $conversation->isDirect())<div class="cx-author">{{ $m->user?->name ?? 'Former user' }}@if($conversation->guests_allowed && $m->user?->user_type === 'client') <span class="cx-ext">Client</span>@endif</div>@endunless
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
                    @if($m->isPoll())
                        @php $poll = $m->pollResults($me); $closed = $m->pollClosed(); @endphp
                        <div class="cx-poll {{ $closed ? 'closed' : '' }}" data-multiple="{{ !empty($m->meta['multiple']) ? 1 : 0 }}">
                            <div class="cx-poll-q"><i class="ri-bar-chart-horizontal-line"></i> {{ $m->body }}</div>
                            <div class="cx-poll-hint">{{ $closed ? 'Poll closed' : (!empty($m->meta['multiple']) ? 'Pick any' : 'Pick one') }} · {{ $poll['voters'] }} {{ \Illuminate\Support\Str::plural('person', $poll['voters']) }} voted</div>
                            @foreach($poll['options'] as $i => $o)
                                <button type="button" class="cx-poll-opt {{ $o['mine'] ? 'me' : '' }}" data-option="{{ $i }}" @disabled($closed) title="{{ $o['names'] ? implode(', ', $o['names']) : 'No votes yet' }}" aria-pressed="{{ $o['mine'] ? 'true' : 'false' }}">
                                    <span class="cx-poll-bar" style="width: {{ $o['pct'] }}%"></span>
                                    <span class="cx-poll-label">@if($o['mine'])<i class="ri-checkbox-circle-fill"></i> @endif{{ $o['text'] }}</span>
                                    <span class="cx-poll-count">{{ $o['count'] }}</span>
                                </button>
                            @endforeach
                            @if(!$closed && ($mine || ($isAdmin ?? false)))
                                <button type="button" class="btn btn-link btn-sm p-0 mt-1 cx-poll-close">Close poll</button>
                            @endif
                        </div>
                    @else
                        @if($m->isGif())
                            <img class="cx-gif" src="{{ $m->meta['url'] }}" alt="{{ $m->meta['title'] ?? 'GIF' }}" loading="lazy" referrerpolicy="no-referrer" @if(!empty($m->meta['width']) && !empty($m->meta['height'])) width="{{ $m->meta['width'] }}" height="{{ $m->meta['height'] }}" @endif>
                            <span class="cx-gif-tag">GIPHY</span>
                        @endif
                        @if($m->kind === 'recording' && !empty($m->meta['recording_id']))
                            <a class="cx-file cx-rec" href="{{ route('connect.calls.recap', $m->meta['call_id']) }}"><i class="{{ !empty($m->meta['audio']) ? 'ri-mic-line' : 'ri-movie-2-line' }}"></i> <span>Call recording<small class="d-block">{{ $m->meta['duration'] ?? '' }} · open the recap to play or download</small></span></a>
                        @elseif($m->body)<div class="cx-text">{!! $m->html($me) !!}</div>@endif
                    @endif
                @endif
                <span class="cx-stamp">@if($isSaved)<i class="ri-bookmark-fill" title="Saved"></i> @endif{{ $m->created_at->format('H:i') }}@if($m->edited_at && !$m->isDeleted()) · edited @endif</span>
            </div>
            @if($m->reactions->isNotEmpty())
                <div class="cx-reactions">
                    @foreach($m->reactions->groupBy('emoji') as $emoji => $rs)
                        <button type="button" class="cx-react {{ $rs->contains('user_id', $me->id) ? 'me' : '' }}" data-emoji="{{ $emoji }}" title="{{ $rs->map(fn ($r) => $r->user_id === $me->id ? 'You' : ($r->user?->name ?? 'Someone'))->implode(', ') }} reacted with {{ $emoji }}">{{ $emoji }} {{ $rs->count() }}</button>
                    @endforeach
                    <button type="button" class="cx-react cx-react-add" aria-label="Add a reaction" title="Add a reaction"><i class="ri-emotion-line"></i><i class="ri-add-line"></i></button>
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
                        <div class="px-2 pb-1 d-flex gap-1">@foreach(\App\Models\Connect\Reaction::QUICK as $e)<button type="button" class="btn btn-sm btn-light cx-react" data-emoji="{{ $e }}">{{ $e }}</button>@endforeach<button type="button" class="btn btn-sm btn-light cx-react-add" title="More reactions" aria-label="More reactions"><i class="ri-add-line"></i></button></div>
                        <button type="button" class="dropdown-item cx-reply" data-preview="{{ $m->preview(60) }}" data-name="{{ $m->user?->name }}"><i class="ri-reply-line"></i> Reply</button>
                        @if(!$m->thread_id && !$inThread)<button type="button" class="dropdown-item cx-thread-open"><i class="ri-chat-thread-line"></i> Reply in thread</button>@endif
                        <button type="button" class="dropdown-item cx-pin"><i class="ri-pushpin-2-line"></i> {{ $m->pinned_at ? 'Unpin' : 'Pin' }}</button>
                        <button type="button" class="dropdown-item cx-remind"><i class="ri-alarm-line"></i> Remind me about this</button>
                        <button type="button" class="dropdown-item cx-save"><i class="{{ $isSaved ? 'ri-bookmark-fill' : 'ri-bookmark-line' }}"></i> {{ $isSaved ? 'Remove from saved' : 'Save' }}</button>
                        @if($mine && !in_array($m->kind, ['poll', 'recording'], true))<button type="button" class="dropdown-item cx-edit"><i class="ri-edit-line"></i> Edit</button>@endif
                        @if($mine || ($isAdmin ?? false))<button type="button" class="dropdown-item text-danger cx-delete"><i class="ri-delete-bin-line"></i> Delete</button>@endif
                    </div>
                </div>
            </div>
        @endunless
    </div>
@endif
