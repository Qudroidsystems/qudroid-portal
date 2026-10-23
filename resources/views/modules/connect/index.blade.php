@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @include('partials.flash')

    <div class="cx-shell {{ $conversation ? 'has-open' : '' }}">
        {{-- ── Conversation list ───────────────────────────────────────── --}}
        <aside class="cx-side">
            <div class="cx-side-head">
                <h5 class="mb-0"><i class="ri-chat-smile-3-line"></i> Connect</h5>
                <div class="d-flex gap-1">
                    <button class="btn btn-sm btn-soft-primary" data-bs-toggle="modal" data-bs-target="#cxNewChat" title="New message"><i class="ri-edit-box-line"></i></button>
                    @unless($isGuest ?? false)
                        <button class="btn btn-sm btn-soft-secondary" data-bs-toggle="modal" data-bs-target="#cxNewGroup" title="New group"><i class="ri-group-line"></i></button>
                        <button class="btn btn-sm btn-soft-secondary" data-bs-toggle="modal" data-bs-target="#cxChannels" title="Channels"><i class="ri-hashtag"></i></button>
                    @endunless
                    <a href="{{ route('connect.calls.index') }}" class="btn btn-sm btn-soft-secondary" title="Calls" aria-label="Call history"><i class="ri-phone-line"></i></a>
                </div>
            </div>
            <form action="{{ route('connect.search') }}" class="cx-search">
                <i class="ri-search-line"></i><input name="q" class="form-control form-control-sm" placeholder="Search messages and files" aria-label="Search messages">
                @php $laterCount = \App\Models\Connect\Reminder::where('user_id', $me->id)->open()->where('remind_at', '<=', now())->count(); @endphp
                <a href="{{ route('connect.later') }}" class="btn btn-sm btn-ghost-secondary position-relative" title="Later: reminders and scheduled messages" aria-label="Later"><i class="ri-alarm-line"></i>@if($laterCount)<span class="badge rounded-pill bg-danger cx-later-badge">{{ $laterCount }}</span>@endif</a>
                <a href="{{ route('connect.saved') }}" class="btn btn-sm btn-ghost-secondary" title="Saved messages" aria-label="Saved messages"><i class="ri-bookmark-line"></i></a>
            </form>
            <div class="cx-list" id="cxInbox">
                @include('modules.connect._inbox', ['active' => $conversation])
            </div>
        </aside>

        {{-- ── Open conversation ───────────────────────────────────────── --}}
        <section class="cx-main">
            @if(!$conversation)
                <div class="cx-empty">
                    <i class="ri-chat-smile-3-line"></i>
                    <h5>Qudroid Connect</h5>
                    <p class="text-muted">{{ ($isGuest ?? false) ? 'Your conversations with the Qudroid team show here.' : 'Pick a conversation, or start a new one.' }}</p>
                </div>
            @else
                <div class="cx-head">
                    <a href="{{ route('connect.index') }}" class="btn btn-sm btn-ghost-secondary d-lg-none"><i class="ri-arrow-left-line"></i></a>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $conversation->titleFor($me) }}@if($conversation->guests_allowed) <span class="cx-ext" title="Clients can be in this chat">External</span>@endif</div>
                        <div class="small text-muted text-truncate">
                            @if($conversation->isDirect())
                                @php $other = $conversation->otherUser($me); @endphp
                                @php $otherPresence = app(\App\Services\Presence\Presence::class)->of($other); @endphp
                                <span class="cx-pdot" id="cxPresenceDot" style="background: {{ $otherPresence['color'] }}"></span>
                                <span id="cxPresence" class="{{ $otherPresence['state'] === 'online' ? 'text-success' : ($otherPresence['dnd'] ? 'text-danger' : '') }}">{{ app(\App\Services\Presence\Presence::class)->line($other) }}</span>
                            @else
                                {{ $conversation->members->count() }} {{ \Illuminate\Support\Str::plural('member', $conversation->members->count()) }}
                                @if($conversation->description) · {{ $conversation->description }}@endif
                                @if($conversation->project) · <a href="{{ route('projects.show', $conversation->project) }}">project</a>@endif
                                @if($conversation->meeting_id) · <a href="{{ route('meetings.show', $conversation->meeting_id) }}">meeting</a>@endif
                            @endif
                        </div>
                    </div>
                    @if($participant && \Illuminate\Support\Facades\Route::has('connect.calls.start'))
                        <form method="POST" action="{{ route('connect.calls.start', $conversation) }}" target="_blank" class="d-flex gap-1">
                            @csrf
                            <button name="kind" value="audio" class="btn btn-sm btn-ghost-secondary" title="Voice call" aria-label="Start a voice call"><i class="ri-phone-line"></i></button>
                            <button name="kind" value="video" class="btn btn-sm btn-ghost-secondary" title="Video call" aria-label="Start a video call"><i class="ri-vidicon-line"></i></button>
                            @unless($conversation->isDirect())<button type="button" class="btn btn-sm btn-ghost-secondary" id="cxHuddleBtn" title="Start or join a huddle: a drop-in voice room that doesn't ring anyone" aria-label="Huddle"><i class="ri-headphone-line"></i></button>@endunless
                        </form>
                    @endif
                    @if($pinned->isNotEmpty())
                        <div class="dropdown">
                            <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="dropdown" title="Pinned messages" aria-label="Pinned messages"><i class="ri-pushpin-2-line"></i><span class="small ms-1">{{ $pinned->count() }}</span></button>
                            <div class="dropdown-menu dropdown-menu-end cx-pins">
                                <h6 class="dropdown-header">Pinned</h6>
                                @foreach($pinned as $pm)
                                    <a class="dropdown-item" href="{{ $pm->thread_id ? route('connect.show', [$conversation, 'around' => $pm->thread_id, 'thread' => $pm->thread_id]) : route('connect.show', [$conversation, 'around' => $pm->id]) . '#m' . $pm->id }}">
                                        <span class="small text-muted d-block">{{ $pm->user?->name }} · {{ $pm->created_at->format('j M') }}</span>{{ $pm->preview(80) }}
                                    </a>
                                @endforeach
                            </div>
                        </div>
                    @endif
                    @php $noteCount = \App\Models\Connect\Note::where('conversation_id', $conversation->id)->count(); @endphp
                    <a href="{{ route('connect.notes.index', $conversation) }}" class="btn btn-sm btn-ghost-secondary" title="Notes and files" aria-label="Notes and files"><i class="ri-sticky-note-line"></i>@if($noteCount)<span class="small ms-1">{{ $noteCount }}</span>@endif</a>
                    @if($participant)
                        <form method="POST" action="{{ route('connect.mute', $conversation) }}">
                            @csrf <input type="hidden" name="muted" value="{{ $participant->muted ? 0 : 1 }}">
                            <button class="btn btn-sm btn-ghost-secondary" title="{{ $participant->muted ? 'Unmute' : 'Mute' }}"><i class="{{ $participant->muted ? 'ri-notification-off-line' : 'ri-notification-3-line' }}"></i></button>
                        </form>
                    @endif
                    @unless($conversation->isDirect())
                        <button class="btn btn-sm btn-ghost-secondary" data-bs-toggle="modal" data-bs-target="#cxMembers" title="People"><i class="ri-team-line"></i></button>
                    @endunless
                </div>

                @if($conversation->guests_allowed && !($isGuest ?? false))
                    <div class="cx-ext-bar"><i class="ri-earth-line"></i> Shared with clients. Everything here, including files and notes, is visible to the clients in this chat.</div>
                @endif
                <div id="cxCallBar" aria-live="polite"></div>
                <div class="cx-scroll" id="cxScroll">
                    @if($messages->count() >= 50)
                        <div class="text-center my-2"><button class="btn btn-sm btn-soft-secondary" id="cxOlder">Load earlier messages</button></div>
                    @endif
                    <div id="cxMessages">
                        @foreach($messages as $m)
                            @include('modules.connect._message', ['m' => $m])
                        @endforeach
                    </div>
                </div>

                @if($participant)
                    <div class="cx-typing" id="cxTyping" aria-live="polite"></div>
                    <form class="cx-composer" id="cxComposer" method="POST" action="{{ route('connect.send', $conversation) }}" enctype="multipart/form-data">
                        @csrf
                        <div class="cx-suggest d-none"></div>
                        <input type="hidden" name="reply_to_id" id="cxReplyTo">
                        <div class="cx-replying d-none" id="cxReplying"><span></span><button type="button" class="btn btn-sm btn-ghost-secondary" id="cxReplyCancel"><i class="ri-close-line"></i></button></div>
                        <div class="cx-panel d-none" id="cxGifPanel" aria-label="GIFs">
                            <div class="d-flex gap-2 mb-2"><input type="search" class="form-control form-control-sm" id="cxGifQ" placeholder="Search GIPHY" autocomplete="off" aria-label="Search GIFs"><button type="button" class="btn btn-sm btn-ghost-secondary cx-panel-close" aria-label="Close"><i class="ri-close-line"></i></button></div>
                            <div class="cx-gif-grid" id="cxGifGrid"></div>
                            <div class="small text-muted mt-1 text-end">Powered by GIPHY</div>
                        </div>
                        <div class="cx-panel d-none" id="cxPollPanel" aria-label="New poll">
                            <div class="d-flex justify-content-between align-items-center mb-2"><strong><i class="ri-bar-chart-horizontal-line"></i> New poll</strong><button type="button" class="btn btn-sm btn-ghost-secondary cx-panel-close" aria-label="Close"><i class="ri-close-line"></i></button></div>
                            <input class="form-control form-control-sm mb-2" id="cxPollQ" maxlength="300" placeholder="Ask a question" aria-label="Question">
                            <div id="cxPollOpts">
                                <input class="form-control form-control-sm mb-1 cx-poll-in" maxlength="120" placeholder="Option 1" aria-label="Option 1">
                                <input class="form-control form-control-sm mb-1 cx-poll-in" maxlength="120" placeholder="Option 2" aria-label="Option 2">
                            </div>
                            <div class="d-flex flex-wrap gap-2 align-items-center mt-1">
                                <button type="button" class="btn btn-sm btn-ghost-secondary" id="cxPollAdd"><i class="ri-add-line"></i> Add option</button>
                                <div class="form-check mb-0"><input type="checkbox" class="form-check-input" id="cxPollMulti"><label class="form-check-label small" for="cxPollMulti">People can pick more than one</label></div>
                                <button type="button" class="btn btn-sm btn-primary ms-auto" id="cxPollSend">Post poll</button>
                            </div>
                        </div>
                        @php $myScheduled = \App\Models\Connect\ScheduledMessage::pending()->where('conversation_id', $conversation->id)->where('user_id', $me->id)->count(); @endphp
                        @if($myScheduled)
                            <a href="{{ route('connect.later') }}" class="cx-sched-chip"><i class="ri-time-line"></i> {{ $myScheduled }} scheduled {{ \Illuminate\Support\Str::plural('message', $myScheduled) }} here</a>
                        @endif
                        <div class="cx-panel d-none" id="cxSchedPanel" aria-label="Schedule message">
                            <div class="d-flex justify-content-between align-items-center mb-2"><strong><i class="ri-time-line"></i> Send later</strong><button type="button" class="btn btn-sm btn-ghost-secondary cx-panel-close" aria-label="Close"><i class="ri-close-line"></i></button></div>
                            <div class="d-flex flex-wrap gap-1 mb-2">
                                @foreach(\App\Services\Connect\LaterService::CHOICES as $k => $v)<button type="button" class="cx-sched-opt" data-when="{{ $k }}">{{ $v }}</button>@endforeach
                            </div>
                            <div class="d-flex gap-2"><input type="datetime-local" class="form-control form-control-sm" id="cxSchedAt" aria-label="Pick a date and time" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}"><button type="button" class="btn btn-sm btn-primary" id="cxSchedGo">Schedule</button></div>
                            <div class="small text-muted mt-1">Write the message first. It's sent from you at that time.</div>
                        </div>
                        <div class="cx-file-chosen d-none" id="cxFileChosen"><i class="ri-attachment-2"></i> <span></span><button type="button" class="btn btn-sm btn-ghost-secondary" id="cxFileCancel"><i class="ri-close-line"></i></button></div>
                        <div class="d-flex gap-2 align-items-end">
                            <div class="dropup">
                                <button type="button" class="btn btn-ghost-secondary" data-bs-toggle="dropdown" title="Attach" aria-label="Attach"><i class="ri-attachment-2 fs-5"></i></button>
                                <div class="dropdown-menu">
                                    <label class="dropdown-item mb-0" role="button"><i class="ri-upload-2-line me-2"></i>Upload a file<input type="file" name="attachment" id="cxFile" hidden></label>
                                    @if(\Illuminate\Support\Facades\Route::has('drive.send') && !($isGuest ?? false))<button type="button" class="dropdown-item" data-bs-toggle="modal" data-bs-target="#cxDrive"><i class="ri-folder-cloud-line me-2"></i>From Drive</button>@endif
                                </div>
                            </div>
                            <button type="button" class="btn btn-ghost-secondary cx-tool" id="cxEmojiBtn" title="Emoji" aria-label="Emoji"><i class="ri-emotion-happy-line fs-5"></i></button>
                            <button type="button" class="btn btn-ghost-secondary cx-tool cx-gif-btn" id="cxGifBtn" title="GIF" aria-label="GIF" aria-expanded="false">GIF</button>
                            <button type="button" class="btn btn-ghost-secondary cx-tool" id="cxPollBtn" title="Poll" aria-label="Create a poll" aria-expanded="false"><i class="ri-bar-chart-horizontal-line fs-5"></i></button>
                            <textarea name="body" id="cxBody" rows="1" class="form-control cx-input" maxlength="5000" placeholder="Write a message. @ to mention someone. Enter sends, Shift+Enter for a new line" aria-label="Message"></textarea>
                            <div class="btn-group">
                                <button class="btn btn-primary" id="cxSend" aria-label="Send"><i class="ri-send-plane-2-fill"></i></button>
                                <button type="button" class="btn btn-primary cx-sched-btn" id="cxSchedBtn" title="Send later" aria-label="Send later" aria-expanded="false"><i class="ri-time-line"></i></button>
                            </div>
                        </div>
                    </form>
                @elseif($conversation->type === 'channel')
                    <div class="cx-composer text-center">
                        <form method="POST" action="{{ route('connect.join', $conversation) }}">@csrf<button class="btn btn-primary">Join #{{ $conversation->name }}</button></form>
                    </div>
                @endif
            @endif
        </section>

        @if($conversation)
            <aside class="cx-thread" id="cxThread" aria-label="Thread">
                <div class="cx-head">
                    <div class="flex-grow-1 fw-semibold"><i class="ri-chat-thread-line"></i> Thread</div>
                    <button type="button" class="btn btn-sm btn-ghost-secondary" id="cxThreadClose" aria-label="Close thread"><i class="ri-close-line"></i></button>
                </div>
                <div class="cx-scroll" id="cxThreadScroll">
                    <div id="cxThreadRoot"></div>
                    <div class="cx-thread-sep" id="cxThreadSep"></div>
                    <div id="cxThreadMessages"></div>
                </div>
                @if($participant)
                    <form class="cx-composer" id="cxThreadComposer" method="POST" action="{{ route('connect.send', $conversation) }}">
                        @csrf
                        <input type="hidden" name="thread_id" id="cxThreadId">
                        <div class="cx-suggest d-none"></div>
                        <div class="d-flex gap-2 align-items-end">
                            <textarea name="body" rows="1" class="form-control cx-input" maxlength="5000" placeholder="Reply in thread" aria-label="Reply in thread"></textarea>
                            <button class="btn btn-primary" aria-label="Send reply"><i class="ri-send-plane-2-fill"></i></button>
                        </div>
                    </form>
                @endif
            </aside>
        @endif
    </div>

    {{-- ── Huddle dock ──────────────────────────────────────────────────── --}}
    <div class="cx-dock d-none" id="cxDock" role="dialog" aria-label="Huddle">
        <div class="cx-dock-head">
            <i class="ri-headphone-line"></i><span class="flex-grow-1 text-truncate" id="cxDockTitle">Huddle</span>
            <button type="button" class="btn btn-sm btn-ghost-light" id="cxDockSize" title="Bigger" aria-label="Make bigger"><i class="ri-fullscreen-line"></i></button>
            <button type="button" class="btn btn-sm btn-ghost-light" id="cxDockPop" title="Open in a new tab" aria-label="Open in a new tab"><i class="ri-external-link-line"></i></button>
            <button type="button" class="btn btn-sm btn-ghost-light" id="cxDockClose" title="Leave" aria-label="Leave the huddle"><i class="ri-close-line"></i></button>
        </div>
        <div class="cx-dock-body" id="cxDockBody"></div>
    </div>

    {{-- ── New direct message ─────────────────────────────────────────── --}}
    <div class="modal fade" id="cxNewChat" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">New message</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <input class="form-control mb-2 cx-filter" data-target="#cxPeopleList" placeholder="Search people">
            <div class="list-group" id="cxPeopleList">
                @foreach($people as $p)
                    <a href="{{ route('connect.direct', $p) }}" class="list-group-item list-group-item-action d-flex gap-2 align-items-center" data-search="{{ strtolower($p->name . ' ' . $p->email) }}">
                        <img src="{{ $p->avatar_url }}" class="rounded-circle" width="28" height="28" alt="">
                        <span>{{ $p->name }}<small class="d-block text-muted">{{ $p->user_type_label }}</small></span>
                    </a>
                @endforeach
            </div>
        </div>
    </div></div></div>

    {{-- ── New group ──────────────────────────────────────────────────── --}}
    <div class="modal fade" id="cxNewGroup" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <form method="POST" action="{{ route('connect.groups.store') }}" class="d-contents">
            @csrf
            <div class="modal-header"><h5 class="modal-title">New group</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <input name="name" class="form-control mb-2" maxlength="100" required placeholder="Group name">
                <input class="form-control form-control-sm mb-2 cx-filter" data-target="#cxGroupPeople" placeholder="Search people">
                <div id="cxGroupPeople" style="max-height: 50vh; overflow:auto">
                    @foreach($people as $p)
                        <label class="d-flex gap-2 align-items-center py-1" data-search="{{ strtolower($p->name . ' ' . $p->email) }}"><input type="checkbox" name="users[]" value="{{ $p->id }}" class="form-check-input mt-0"> {{ $p->name }}</label>
                    @endforeach
                </div>
            </div>
            <div class="modal-footer"><button class="btn btn-primary">Create group</button></div>
        </form>
    </div></div></div>

    {{-- ── Channels ───────────────────────────────────────────────────── --}}
    <div class="modal fade" id="cxChannels" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
        <div class="modal-header"><h5 class="modal-title">Channels</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
        <div class="modal-body">
            <div class="list-group mb-3">
                @forelse($channels as $ch)
                    <a href="{{ route('connect.show', $ch) }}" class="list-group-item list-group-item-action"><strong>#{{ $ch->name }}</strong> <span class="text-muted small">· {{ $ch->members_count }} {{ \Illuminate\Support\Str::plural('member', $ch->members_count) }}</span>@if($ch->description)<div class="small text-muted">{{ $ch->description }}</div>@endif</a>
                @empty
                    <div class="text-muted small">No channels yet.</div>
                @endforelse
            </div>
            @if($me->isStaff())
                <form method="POST" action="{{ route('connect.channels.store') }}" class="border-top pt-3">
                    @csrf
                    <label class="form-label">New channel</label>
                    <div class="input-group mb-2"><span class="input-group-text">#</span><input name="name" class="form-control" maxlength="60" required placeholder="general"></div>
                    <input name="description" class="form-control mb-2" maxlength="255" placeholder="What it's for (optional)">
                    <button class="btn btn-primary btn-sm">Create channel</button>
                </form>
            @endif
        </div>
    </div></div></div>

    {{-- ── Share from Drive ───────────────────────────────────────────── --}}
    @if($conversation && ($participant ?? null) && !($isGuest ?? false) && \Illuminate\Support\Facades\Route::has('drive.send'))
        <div class="modal fade" id="cxDrive" tabindex="-1" aria-labelledby="cxDriveTitle"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title" id="cxDriveTitle">Share from Drive</h5><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body">
                <p class="small text-muted">Everyone here gets view access to what you send.</p>
                <div class="list-group">
                    @forelse($driveFiles as $f)
                        <form method="POST" action="{{ route('drive.send', ['type' => 'file', 'id' => $f->id]) }}" class="list-group-item d-flex align-items-center gap-2">
                            @csrf <input type="hidden" name="conversation_id" value="{{ $conversation->id }}">
                            <i class="{{ $f->icon() }} fs-5"></i><span class="text-truncate flex-grow-1">{{ $f->name }}<small class="d-block text-muted">{{ $f->updated_at->diffForHumans() }}</small></span>
                            <button class="btn btn-sm btn-soft-primary">Send</button>
                        </form>
                    @empty
                        <div class="text-muted small">No files in your Drive yet.</div>
                    @endforelse
                </div>
            </div>
            <div class="modal-footer"><a href="{{ route('drive.index') }}" class="btn btn-light btn-sm">Open Drive</a></div>
        </div></div></div>
    @endif

    {{-- ── Members ────────────────────────────────────────────────────── --}}
    @if($conversation && !$conversation->isDirect())
        <div class="modal fade" id="cxMembers" tabindex="-1"><div class="modal-dialog modal-dialog-scrollable"><div class="modal-content">
            <div class="modal-header"><h5 class="modal-title">People in {{ $conversation->titleFor($me) }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <ul class="list-group mb-3">
                    @foreach($conversation->members->sortBy('user.name') as $p)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><img src="{{ $p->user->avatar_url }}" class="rounded-circle me-2" width="24" height="24" alt="">{{ $p->user->name }}@if($p->role === 'admin') <span class="badge bg-light text-muted">admin</span>@endif @if(!$p->user->can('Use connect'))<span class="cx-ext">Client</span>@endif</span>
                            @if($isAdmin && $p->user_id !== $me->id && !$conversation->isManaged())
                                <form method="POST" action="{{ route('connect.members.remove', [$conversation, $p->user]) }}" onsubmit="return confirm('Remove {{ e($p->user->name) }}?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-close-line"></i></button></form>
                            @endif
                        </li>
                    @endforeach
                </ul>
                @if($participant && !$conversation->isManaged() && !($isGuest ?? false) && ($isAdmin || $conversation->type === 'channel'))
                    <form method="POST" action="{{ route('connect.members.add', $conversation) }}" class="mb-3">
                        @csrf
                        <label class="form-label">Add people</label>
                        <select name="users[]" class="form-select mb-2" multiple size="6">
                            <optgroup label="Team">
                                @foreach($people->whereNotIn('id', $conversation->members->pluck('user_id')) as $p)<option value="{{ $p->id }}">{{ $p->name }}</option>@endforeach
                            </optgroup>
                            @if($conversation->guests_allowed)
                                <optgroup label="Clients">
                                    @foreach(($guests ?? collect())->whereNotIn('id', $conversation->members->pluck('user_id')) as $p)<option value="{{ $p->id }}">{{ $p->name }} ({{ $p->email }})</option>@endforeach
                                </optgroup>
                            @endif
                        </select>
                        <button class="btn btn-sm btn-primary">Add</button>
                    </form>
                @endif
                @if($isAdmin && $conversation->type === 'group' && !$conversation->isManaged())
                    <form method="POST" action="{{ route('connect.guests', $conversation) }}" class="mb-3 d-flex align-items-center gap-2 border rounded p-2">
                        @csrf <input type="hidden" name="on" value="{{ $conversation->guests_allowed ? 0 : 1 }}">
                        <div class="flex-grow-1 small"><strong>{{ $conversation->guests_allowed ? 'Shared with clients' : 'Team only' }}</strong><div class="text-muted">{{ $conversation->guests_allowed ? 'Clients can be added to this group.' : 'Turn on sharing to add clients to this group.' }}</div></div>
                        <button class="btn btn-sm {{ $conversation->guests_allowed ? 'btn-soft-secondary' : 'btn-soft-primary' }}">{{ $conversation->guests_allowed ? 'Make team only' : 'Share with clients' }}</button>
                    </form>
                @endif
                @if($participant && !$conversation->isManaged())
                    <form method="POST" action="{{ route('connect.leave', $conversation) }}" onsubmit="return confirm('Leave this conversation?')">@csrf<button class="btn btn-sm btn-soft-danger">Leave</button></form>
                @endif
                @if($conversation->project_id)<div class="small text-muted">This chat follows the project team. Change people on the project.</div>@endif
                @if($conversation->meeting_id)<div class="small text-muted">This chat follows the meeting's invite list. <a href="{{ route('meetings.show', $conversation->meeting_id) }}">Open the meeting</a>.</div>@endif
            </div>
        </div></div></div>
    @endif

</div></div></div>
@endsection

@push('styles')
<style>
    .cx-shell { display: grid; grid-template-columns: 320px 1fr; height: calc(100vh - 170px); min-height: 480px; background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: var(--cb-radius); overflow: hidden; }
    .cx-side { border-right: 1px solid var(--cb-border); display: flex; flex-direction: column; min-height: 0; }
    .cx-side-head { display: flex; justify-content: space-between; align-items: center; padding: 14px; border-bottom: 1px solid var(--cb-border); }
    .cx-list { overflow-y: auto; flex: 1; }
    .cx-item { display: flex; gap: 10px; padding: 10px 14px; color: var(--cb-text); text-decoration: none; border-bottom: 1px solid var(--cb-border); }
    .cx-item:hover, .cx-item.active { background: var(--cb-hover); color: var(--cb-text); }
    .cx-item.unread .cx-title, .cx-item.unread .cx-preview { font-weight: 700; color: var(--cb-heading); }
    .cx-avatar { flex: 0 0 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; background: var(--cb-surface-2); color: var(--cb-teal); font-size: 18px; overflow: hidden; }
    .cx-avatar img { width: 100%; height: 100%; object-fit: cover; }
    .cx-meta { flex: 1; min-width: 0; display: flex; flex-direction: column; gap: 2px; }
    .cx-title { color: var(--cb-heading); font-weight: 600; }
    .cx-time, .cx-preview { font-size: 12px; color: var(--cb-muted); }
    .cx-main { display: flex; flex-direction: column; min-height: 0; min-width: 0; }
    .cx-empty { margin: auto; text-align: center; }
    .cx-empty i { font-size: 56px; color: var(--cb-teal); }
    .cx-ext { display: inline-block; font-size: 11px; font-weight: 600; line-height: 1; padding: 3px 6px; border-radius: 4px; background: #fef3c7; color: #92400e; vertical-align: middle; }
    .cx-ext-bar { display: flex; gap: 6px; align-items: center; padding: 6px 16px; font-size: 13px; background: #fffbeb; color: #92400e; border-bottom: 1px solid #fde68a; }
    .cx-head { display: flex; gap: 8px; align-items: center; padding: 12px 16px; border-bottom: 1px solid var(--cb-border); }
    .min-w-0 { min-width: 0; }
    .cx-scroll { flex: 1; overflow-y: auto; padding: 16px; background: var(--cb-surface-2); }
    .cx-system { text-align: center; font-size: 12px; color: var(--cb-muted); margin: 8px 0; }
    .cx-msg { display: flex; gap: 8px; margin: 6px 0; align-items: flex-end; }
    .cx-msg.mine { flex-direction: row-reverse; }
    .cx-msg-avatar { width: 28px; height: 28px; border-radius: 50%; object-fit: cover; }
    .cx-bubble-wrap { max-width: min(72%, 640px); display: flex; flex-direction: column; }
    .cx-msg.mine .cx-bubble-wrap { align-items: flex-end; }
    .cx-author { font-size: 12px; color: var(--cb-muted); margin: 0 0 2px 4px; }
    .cx-bubble { background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: 14px; padding: 8px 12px 6px; position: relative; word-wrap: break-word; overflow-wrap: anywhere; }
    .cx-msg.mine .cx-bubble { background: #d9f4ef; border-color: #b6e6dc; }
    [data-bs-theme="dark"] .cx-msg.mine .cx-bubble { background: #134e48; border-color: #1b6b62; }
    .cx-bubble.deleted { font-style: italic; color: var(--cb-muted); }
    .cx-stamp { display: block; text-align: right; font-size: 10px; color: var(--cb-muted); margin-top: 2px; }
    .cx-quote { border-left: 3px solid var(--cb-teal); padding: 2px 8px; margin-bottom: 4px; font-size: 12px; color: var(--cb-muted); background: rgba(13,148,136,.06); border-radius: 4px; }
    .cx-image { max-width: 260px; max-height: 260px; border-radius: 10px; display: block; margin-bottom: 4px; }
    .cx-file { display: flex; gap: 8px; align-items: center; padding: 6px 8px; border-radius: 8px; background: rgba(0,0,0,.04); margin-bottom: 4px; }
    .cx-file i { font-size: 22px; }
    .cx-pdot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; margin-right: 4px; vertical-align: middle; }
    .cx-reactions { display: flex; gap: 4px; margin-top: 2px; flex-wrap: wrap; }
    .cx-react-add { color: var(--cb-muted); opacity: 0; transition: opacity .15s; }
    .cx-msg:hover .cx-react-add, .cx-react-add:focus { opacity: 1; }
    .cx-react-add i + i { font-size: 9px; margin-left: -2px; }
    .cx-later-badge { position: absolute; top: -4px; right: -4px; font-size: 9px; }
    .cx-sched-btn { border-left: 1px solid rgba(255,255,255,.35); padding-left: .45rem; padding-right: .45rem; }
    .cx-sched-chip { display: inline-block; font-size: 12px; margin-bottom: 6px; color: var(--cb-muted); }
    .cx-sched-opt { border: 1px solid var(--cb-border); background: var(--cb-surface-2); border-radius: 14px; font-size: 12px; padding: 2px 10px; color: var(--cb-text); }
    .cx-sched-opt:hover { border-color: var(--cb-teal); }
    .cx-tool { padding-left: .45rem; padding-right: .45rem; }
    .cx-gif-btn { font-size: 11px; font-weight: 800; letter-spacing: .5px; }
    .cx-panel { border: 1px solid var(--cb-border); border-radius: var(--cb-radius-sm); background: var(--cb-surface); padding: 10px; margin-bottom: 8px; }
    .cx-gif-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(110px, 1fr)); gap: 6px; max-height: 260px; overflow-y: auto; }
    .cx-gif-grid button { border: 0; padding: 0; background: var(--cb-surface-2); border-radius: 8px; overflow: hidden; height: 100px; }
    .cx-gif-grid img { width: 100%; height: 100%; object-fit: cover; display: block; }
    .cx-gif { display: block; max-width: min(280px, 100%); height: auto; border-radius: 10px; margin: 2px 0 4px; }
    .cx-gif-tag { font-size: 9px; font-weight: 700; color: var(--cb-muted); letter-spacing: .5px; }
    .cx-poll { min-width: min(320px, 70vw); }
    .cx-poll-q { font-weight: 600; color: var(--cb-heading); margin-bottom: 2px; }
    .cx-poll-hint { font-size: 12px; color: var(--cb-muted); margin-bottom: 6px; }
    .cx-poll-opt { position: relative; display: flex; justify-content: space-between; gap: 8px; width: 100%; text-align: left; border: 1px solid var(--cb-border); background: var(--cb-surface); border-radius: 8px; padding: 6px 10px; margin-bottom: 4px; overflow: hidden; color: var(--cb-text); }
    .cx-poll-opt:not(:disabled):hover { border-color: var(--cb-teal); }
    .cx-poll-opt.me { border-color: var(--cb-teal); }
    .cx-poll-bar { position: absolute; inset: 0 auto 0 0; background: rgba(13,148,136,.14); transition: width .3s; }
    .cx-poll-label, .cx-poll-count { position: relative; }
    .cx-poll-label i { color: var(--cb-teal); }
    .cx-poll-count { font-weight: 600; font-variant-numeric: tabular-nums; }
    .cx-poll.closed .cx-poll-opt { cursor: default; }
    #cxEmojiPop { position: fixed; z-index: 1080; width: 320px; max-width: calc(100vw - 16px); background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: 12px; box-shadow: 0 12px 32px rgba(15,35,66,.18); padding: 8px; }
    #cxEmojiPop .cx-em-tabs { display: flex; gap: 2px; border-bottom: 1px solid var(--cb-border); padding-bottom: 6px; margin-bottom: 6px; overflow-x: auto; }
    #cxEmojiPop .cx-em-tabs button { border: 0; background: none; font-size: 12px; padding: 2px 8px; border-radius: 6px; color: var(--cb-muted); white-space: nowrap; }
    #cxEmojiPop .cx-em-tabs button.active { background: var(--cb-surface-2); color: var(--cb-heading); font-weight: 600; }
    #cxEmojiPop .cx-em-grid { display: grid; grid-template-columns: repeat(8, 1fr); gap: 2px; max-height: 220px; overflow-y: auto; }
    #cxEmojiPop .cx-em-grid button { border: 0; background: none; font-size: 22px; line-height: 1.2; padding: 3px; border-radius: 6px; }
    #cxEmojiPop .cx-em-grid button:hover, #cxEmojiPop .cx-em-grid button:focus { background: var(--cb-surface-2); }
    .cx-react { border: 1px solid var(--cb-border); background: var(--cb-surface); border-radius: 12px; font-size: 12px; padding: 0 6px; }
    .cx-react.me { border-color: var(--cb-teal); background: rgba(13,148,136,.1); }
    .cx-actions { opacity: 0; transition: opacity .15s; }
    .cx-msg:hover .cx-actions, .cx-actions:focus-within { opacity: 1; }
    .cx-composer { border-top: 1px solid var(--cb-border); padding: 10px 12px; background: var(--cb-surface); }
    .cx-composer textarea { resize: none; max-height: 160px; }
    .cx-replying, .cx-file-chosen { display: flex; justify-content: space-between; align-items: center; font-size: 12px; color: var(--cb-muted); border-left: 3px solid var(--cb-teal); padding: 2px 8px; margin-bottom: 6px; }
    .d-contents { display: contents; }
    .cx-shell.has-thread { grid-template-columns: 320px 1fr 360px; }
    .cx-thread { display: none; border-left: 1px solid var(--cb-border); flex-direction: column; min-height: 0; min-width: 0; }
    .cx-shell.has-thread .cx-thread { display: flex; }
    .cx-thread .cx-bubble-wrap { max-width: 85%; }
    .cx-thread-sep { border-top: 1px solid var(--cb-border); margin: 10px 0; }
    .cx-thread-link { border: 0; background: none; padding: 2px 4px; font-size: 12px; color: var(--cb-teal); font-weight: 600; text-align: left; }
    .cx-thread-link:hover { text-decoration: underline; }
    .cx-search { display: flex; align-items: center; gap: 6px; padding: 8px 12px; border-bottom: 1px solid var(--cb-border); }
    .cx-search > i { color: var(--cb-muted); }
    .cx-avatar { position: relative; overflow: visible; }
    .cx-avatar img { border-radius: 50%; }
    .cx-dot { position: absolute; right: 0; bottom: 0; width: 11px; height: 11px; border-radius: 50%; background: #22c55e; border: 2px solid var(--cb-surface); }
    .cx-typing { font-size: 12px; color: var(--cb-muted); padding: 0 16px; min-height: 18px; background: var(--cb-surface-2); }
    .cx-mention { color: var(--cb-teal); font-weight: 600; }
    .cx-mention.me { background: rgba(247, 184, 75, .25); color: inherit; border-radius: 4px; padding: 0 2px; }
    .cx-msg.mentioned .cx-bubble { border-color: #f7b84b; }
    .cx-msg.flash .cx-bubble { animation: cxFlash 2s ease-out; }
    @keyframes cxFlash { from { box-shadow: 0 0 0 3px rgba(247, 184, 75, .8); } to { box-shadow: 0 0 0 3px transparent; } }
    .cx-pinned-tag { font-size: 11px; color: var(--cb-muted); margin-bottom: 2px; }
    .cx-pins { max-width: 340px; max-height: 60vh; overflow: auto; }
    .cx-pins .dropdown-item { white-space: normal; }
    .cx-seen { font-size: 11px; color: var(--cb-muted); text-align: right; margin: -2px 40px 6px 0; }
    .cx-composer { position: relative; }
    .cx-dock { position: fixed; right: 16px; bottom: 16px; width: min(380px, calc(100vw - 32px)); height: 340px; background: #0f1115; border-radius: 14px; box-shadow: 0 16px 40px rgba(0,0,0,.35); z-index: 1060; display: flex; flex-direction: column; overflow: hidden; }
    .cx-dock.big { width: min(820px, calc(100vw - 32px)); height: min(560px, calc(100vh - 100px)); }
    .cx-dock-head { display: flex; align-items: center; gap: 6px; padding: 6px 8px 6px 12px; color: #f3f4f6; font-size: 13px; font-weight: 600; background: #1b1e25; }
    .cx-dock-head .btn { color: #d1d5db; }
    .cx-dock-body { flex: 1; min-height: 0; }
    .cx-dock-body iframe { width: 100%; height: 100%; border: 0; display: block; }
    .cx-live .cx-faces { display: inline-flex; margin-left: 6px; vertical-align: middle; }
    .cx-live .cx-faces img { width: 22px; height: 22px; border-radius: 50%; border: 2px solid var(--cb-surface); margin-left: -6px; object-fit: cover; }
    .cx-live.huddle { background: rgba(124,58,237,.08); }
    .cx-live { display: flex; justify-content: space-between; align-items: center; gap: 8px; padding: 8px 16px; background: rgba(34,197,94,.1); border-bottom: 1px solid var(--cb-border); font-size: 13px; }
    .cx-live-dot { display: inline-block; width: 8px; height: 8px; border-radius: 50%; background: #22c55e; margin-right: 4px; animation: cxBlink 1.2s infinite; }
    @keyframes cxBlink { 50% { opacity: .3; } }
    .cx-suggest { position: absolute; bottom: 100%; left: 12px; right: 12px; max-height: 220px; overflow: auto; background: var(--cb-surface); border: 1px solid var(--cb-border); border-radius: 8px; box-shadow: 0 6px 20px rgba(0,0,0,.08); z-index: 5; }
    .cx-suggest button { display: block; width: 100%; text-align: left; border: 0; background: none; padding: 6px 10px; font-size: 13px; }
    .cx-suggest button.active, .cx-suggest button:hover { background: var(--cb-hover); }
    @media (max-width: 991px) {
        .cx-shell { grid-template-columns: 1fr; height: calc(100vh - 140px); }
        .cx-shell.has-open .cx-side { display: none; }
        .cx-shell.has-thread { grid-template-columns: 1fr; }
        .cx-shell.has-thread .cx-main { display: none; }
        .cx-shell:not(.has-open) .cx-main { display: none; }
        .cx-actions { opacity: 1; }
    }
</style>
@endpush

@push('scripts')
<script>
(function () {
    const token = document.querySelector('meta[name="csrf-token"]')?.content;
    const headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': token, 'X-Requested-With': 'XMLHttpRequest' };
    const post = (url, data) => fetch(url, { method: 'POST', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify(data || {}) });

    // Filter lists in the modals.
    document.querySelectorAll('.cx-filter').forEach(input => input.addEventListener('input', () => {
        const q = input.value.trim().toLowerCase();
        document.querySelectorAll(input.dataset.target + ' [data-search]').forEach(el => el.style.display = !q || el.dataset.search.includes(q) ? '' : 'none');
    }));

    const pollBase = @json(route('connect.poll', $conversation ? [$conversation] : []));
    const badge = document.getElementById('connect-nav-badge');
    const inbox = document.getElementById('cxInbox');
    const shell = document.querySelector('.cx-shell');
    const scroll = document.getElementById('cxScroll');
    const list = document.getElementById('cxMessages');
    const typingEl = document.getElementById('cxTyping');
    const presence = document.getElementById('cxPresence');
    const isDirect = @json($conversation?->isDirect() ?? false);
    let lastId = list ? Math.max(0, ...[...list.querySelectorAll(':scope > [data-id]')].map(e => +e.dataset.id)) : 0;
    let busy = false, ticks = 0, seen = @json($seenUpTo ?? 0);

    // Thread panel state
    const thread = { id: 0, lastId: 0 };
    const tScroll = document.getElementById('cxThreadScroll');
    const tRoot = document.getElementById('cxThreadRoot');
    const tList = document.getElementById('cxThreadMessages');

    const nearBottom = el => !el || el.scrollHeight - el.scrollTop - el.clientHeight < 120;
    const toBottom = el => { if (el) el.scrollTop = el.scrollHeight; };

    // Arriving from a search result, a pin or a notification: show that message.
    const target = location.hash.match(/^#m(\d+)$/) ? document.getElementById(location.hash.slice(1)) : (@json($around) ? document.getElementById('m' + @json($around)) : null);
    if (target) { target.scrollIntoView({ block: 'center' }); target.classList.add('flash'); } else { toBottom(scroll); }

    function mergeInto(container, html, changed) {
        if (!container) return;
        if (html) {
            const tmp = document.createElement('div'); tmp.innerHTML = html;
            tmp.querySelectorAll(':scope > [data-id]').forEach(el => { if (!container.querySelector(':scope > [data-id="' + el.dataset.id + '"]')) container.appendChild(el); });
        }
        Object.entries(changed || {}).forEach(([id, h]) => {
            const old = container.querySelector(':scope > [data-id="' + id + '"]');
            if (old) { const t = document.createElement('div'); t.innerHTML = h; old.replaceWith(t.firstElementChild); }
        });
    }

    function markSeen() {
        if (!isDirect || !list) return;
        list.querySelectorAll('.cx-seen').forEach(e => e.remove());
        const mine = [...list.querySelectorAll(':scope > .cx-msg.mine')].filter(e => +e.dataset.id <= seen);
        const last = mine[mine.length - 1];
        if (last && last === [...list.querySelectorAll(':scope > .cx-msg.mine')].pop()) last.insertAdjacentHTML('afterend', '<div class="cx-seen">Seen</div>');
    }
    markSeen();

    let lastTotal = null, inboxNext = false;
    function poll(force) {
        if (busy || (!force && document.hidden)) return;
        busy = true; ticks++;
        let url = pollBase + '?after=' + lastId + '&since=10' + (inboxNext || ticks % 3 === 1 || (force && !window.QRealtime?.connected) ? '&inbox=1' : '');
        inboxNext = false;
        if (thread.id) url += '&thread=' + thread.id + '&thread_after=' + thread.lastId;
        fetch(url, { headers }).then(r => r.ok ? r.json() : null).then(data => {
            if (!data) return;
            // message tone: new messages from others here, or more unread elsewhere
            if (window.QSound && ((data.incoming || 0) + (data.thread?.incoming || 0) > 0 || (lastTotal !== null && data.total > lastTotal))) QSound.message();
            lastTotal = data.total;
            if (badge) { badge.textContent = data.total; badge.style.display = data.total ? '' : 'none'; }
            if (data.inbox && inbox) inbox.innerHTML = data.inbox;
            if (list) {
                const stick = nearBottom(scroll);
                mergeInto(list, data.html, data.changed);
                if (data.html && stick) toBottom(scroll);
            }
            if (data.last_id) lastId = Math.max(lastId, data.last_id);
            if (data.thread && data.thread.id === thread.id) {
                const stick = nearBottom(tScroll);
                mergeInto(tList, data.thread.html, data.thread.changed);
                thread.lastId = Math.max(thread.lastId, data.thread.last_id || 0);
                if (data.thread.html && stick) toBottom(tScroll);
            }
            if (data.call !== undefined) renderCall(data.call);
            if (typingEl) {
                const t = data.typing || [];
                typingEl.textContent = !t.length ? '' : t.length === 1 ? t[0] + ' is typing…' : t.length === 2 ? t[0] + ' and ' + t[1] + ' are typing…' : 'Several people are typing…';
            }
            if (presence && data.presence !== undefined) {
                presence.textContent = data.presence;
                presence.classList.toggle('text-success', data.presence_state === 'online');
                presence.classList.toggle('text-danger', data.presence_state === 'dnd');
                const pd = document.getElementById('cxPresenceDot'); if (pd && data.presence_color) pd.style.background = data.presence_color;
            }
            if (data.seen !== undefined && data.seen !== seen) { seen = data.seen; markSeen(); } else if (data.html) { markSeen(); }
        }).catch(() => {}).finally(() => busy = false);
    }
    // Every 2 seconds while a conversation is open and on screen, every 6 otherwise. With instant
    // updates (Reverb) connected, the server says when to look, and the timer is only a safety net.
    (function loop() {
        const live = window.QRealtime && QRealtime.connected;
        if (!live) poll();
        setTimeout(loop, live ? 15000 : (list && !document.hidden ? 2000 : 6000));
    })();
    if (window.QRealtime) {
        let soon = null;
        const now = (inbox) => { clearTimeout(soon); soon = setTimeout(() => { inboxNext = inboxNext || inbox; poll(true); }, 120); };
        @if($conversation)
            ['message', 'changed', 'typing', 'read'].forEach(e => QRealtime.on('connect.c.' + @json($conversation->id), e, () => now(false)));
        @endif
        QRealtime.on('user.' + QRealtime.me, 'inbox', () => now(true));
        setInterval(() => { if (QRealtime.connected) poll(true); }, 15000);
    }
    document.addEventListener('visibilitychange', () => { if (!document.hidden) poll(true); });

    // ── Live call / huddle bar and the huddle dock ─────────────────────
    const callBar = document.getElementById('cxCallBar');
    const dock = document.getElementById('cxDock'), dockBody = document.getElementById('cxDockBody');
    let dockCall = null;
    const esc = t => String(t ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    function renderCall(c) {
        if (!callBar) return;
        if (!c) { callBar.innerHTML = ''; return; }
        const names = c.people.map(p => p.name);
        const who = names.length ? (names.length <= 3 ? names.join(', ') : names.slice(0, 2).join(', ') + ' and ' + (names.length - 2) + ' more') : 'nobody yet';
        const inIt = dockCall === c.id;
        callBar.innerHTML = '<div class="cx-live ' + (c.huddle ? 'huddle' : '') + '"><span><span class="cx-live-dot"></span> '
            + (c.huddle ? '<i class="ri-headphone-line"></i> Huddle' : (c.kind === 'audio' ? 'Voice call' : 'Video call') + ' in progress')
            + ' · ' + esc(who) + '<span class="cx-faces">' + c.people.slice(0, 5).map(p => '<img src="' + esc(p.avatar) + '" alt="" title="' + esc(p.name) + '">').join('') + '</span></span>'
            + (inIt ? '<span class="small text-muted">You\'re in it</span>' : (c.huddle ? '<button type="button" class="btn btn-sm btn-success cx-huddle-join" data-url="' + esc(c.url) + '" data-id="' + c.id + '">Join</button>'
                : '<a href="' + esc(c.url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-success">Join</a>')) + '</div>';
    }
    function openDock(url, id) {
        if (dockCall === id) return;
        dockCall = id;
        dockBody.innerHTML = '';
        const f = document.createElement('iframe');
        f.src = url + (url.includes('?') ? '&' : '?') + 'embed=1';
        f.allow = 'microphone; camera; display-capture; autoplay';
        f.title = 'Huddle';
        dockBody.appendChild(f);
        document.getElementById('cxDockTitle').textContent = 'Huddle · ' + @json($conversation?->titleFor($me) ?? '');
        dock.classList.remove('d-none');
        dock.dataset.url = url;
        poll(true);
    }
    function closeDock() { dockBody.innerHTML = ''; dock.classList.add('d-none'); dock.classList.remove('big'); dockCall = null; poll(true); }
    window.addEventListener('message', e => {
        if (e.origin !== location.origin || !e.data || typeof e.data.type !== 'string') return;
        if (e.data.type === 'huddle:close' || e.data.type === 'huddle:ended') setTimeout(closeDock, e.data.type === 'huddle:ended' ? 1500 : 0);
    });
    document.getElementById('cxDockClose')?.addEventListener('click', closeDock);
    document.getElementById('cxDockSize')?.addEventListener('click', () => dock.classList.toggle('big'));
    document.getElementById('cxDockPop')?.addEventListener('click', () => { const u = dock.dataset.url; closeDock(); window.open(u, '_blank', 'noopener'); });
    callBar?.addEventListener('click', e => { const b = e.target.closest('.cx-huddle-join'); if (b) openDock(b.dataset.url, +b.dataset.id); });
    document.getElementById('cxHuddleBtn')?.addEventListener('click', () => {
        fetch(@json($conversation ? route('connect.huddle', $conversation) : ''), { method: 'POST', headers })
            .then(r => r.json().then(j => r.ok ? j : Promise.reject(j)))
            .then(j => j.huddle ? openDock(j.url, j.id) : window.open(j.url, '_blank', 'noopener'))
            .catch(j => alert((j && j.message) || 'The huddle could not be started.'));
    });
    renderCall(@json($callBar ?? null));
    window.addEventListener('beforeunload', e => { if (dockCall) { e.preventDefault(); e.returnValue = ''; } });

    if (!list) return;
    const msgUrl = @json(url('connect/m')) + '/';

    // ── @mentions ──────────────────────────────────────────────────────
    const members = @json($members ?? []);
    if (!isDirect) members.unshift({ id: 'all', name: 'all', hint: 'everyone here' });
    function mentions(input) {
        const box = input.form.querySelector('.cx-suggest');
        if (!box || !members.length) return;
        let items = [], active = 0;
        const query = () => { const m = input.value.slice(0, input.selectionStart).match(/(^|\s)@([\w.'-]*)$/); return m ? m[2].toLowerCase() : null; };
        const close = () => { box.classList.add('d-none'); items = []; };
        const pick = i => {
            const pos = input.selectionStart, before = input.value.slice(0, pos).replace(/@([\w.'-]*)$/, '@' + items[i].name + ' ');
            input.value = before + input.value.slice(pos); input.setSelectionRange(before.length, before.length); close(); input.focus();
        };
        const draw = () => {
            box.innerHTML = items.map((m, i) => '<button type="button" class="' + (i === active ? 'active' : '') + '" data-i="' + i + '">@' + m.name.replace(/[<>&"]/g, '') + (m.hint ? ' <span class="text-muted small">' + m.hint + '</span>' : '') + '</button>').join('');
            box.classList.toggle('d-none', !items.length);
        };
        input.addEventListener('input', () => {
            const q = query();
            if (q === null) return close();
            items = members.filter(m => m.name.toLowerCase().split(' ').some(w => w.startsWith(q)) || m.name.toLowerCase().startsWith(q)).slice(0, 8);
            active = 0; draw();
        });
        input.addEventListener('keydown', e => {
            if (!items.length) return;
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') { e.preventDefault(); active = (active + (e.key === 'ArrowDown' ? 1 : items.length - 1)) % items.length; draw(); }
            else if (e.key === 'Enter' || e.key === 'Tab') { e.preventDefault(); e.stopImmediatePropagation(); pick(active); }
            else if (e.key === 'Escape') close();
        });
        box.addEventListener('mousedown', e => { const b = e.target.closest('[data-i]'); if (b) { e.preventDefault(); pick(+b.dataset.i); } });
        input.addEventListener('blur', () => setTimeout(close, 150));
    }

    // ── Composers (main and thread) ────────────────────────────────────
    const typingUrl = @json($conversation ? route('connect.typing', $conversation) : '');
    let lastTyping = 0;
    function composer(form, after) {
        if (!form) return;
        const input = form.querySelector('textarea');
        const grow = () => { input.style.height = 'auto'; input.style.height = Math.min(160, input.scrollHeight) + 'px'; };
        mentions(input);
        input.addEventListener('input', () => {
            grow();
            if (input.value.trim() && Date.now() - lastTyping > 3000) { lastTyping = Date.now(); post(typingUrl).catch(() => {}); }
        });
        input.addEventListener('keydown', e => { if (e.key === 'Enter' && !e.shiftKey && !e.isComposing) { e.preventDefault(); form.requestSubmit(); } });
        form.addEventListener('submit', e => {
            e.preventDefault();
            const file = form.querySelector('input[type=file]');
            if (!input.value.trim() && !(file && file.files.length)) return;
            const button = form.querySelector('button.btn-primary:not([type=button])');
            button.disabled = true;
            fetch(form.action, { method: 'POST', headers, body: new FormData(form) })
                .then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
                .then(() => { input.value = ''; grow(); lastTyping = 0; after && after(); poll(true); })
                .catch(j => alert((j && j.message) || 'The message was not sent. Please try again.'))
                .finally(() => { button.disabled = false; input.focus(); });
        });
    }

    const form = document.getElementById('cxComposer');
    const file = document.getElementById('cxFile');
    const replyTo = document.getElementById('cxReplyTo');
    const replying = document.getElementById('cxReplying');
    const chosen = document.getElementById('cxFileChosen');
    if (form) {
        composer(form, () => { file.value = ''; chosen.classList.add('d-none'); replyTo.value = ''; replying.classList.add('d-none'); setTimeout(() => toBottom(scroll), 300); });
        file.addEventListener('change', () => { chosen.classList.toggle('d-none', !file.files.length); chosen.querySelector('span').textContent = file.files[0]?.name || ''; });
        document.getElementById('cxFileCancel').addEventListener('click', () => { file.value = ''; chosen.classList.add('d-none'); });
        document.getElementById('cxReplyCancel').addEventListener('click', () => { replyTo.value = ''; replying.classList.add('d-none'); });
    }
    composer(document.getElementById('cxThreadComposer'), () => setTimeout(() => toBottom(tScroll), 300));

    // ── Threads ────────────────────────────────────────────────────────
    function openThread(id) {
        fetch(msgUrl + id + '/thread', { headers }).then(r => r.ok ? r.json() : Promise.reject()).then(data => {
            thread.id = +id; thread.lastId = data.last_id || 0;
            tRoot.innerHTML = data.root; tList.innerHTML = data.html;
            document.getElementById('cxThreadSep').textContent = '';
            const tid = document.getElementById('cxThreadId'); if (tid) tid.value = id;
            shell.classList.add('has-thread');
            toBottom(tScroll);
            document.querySelector('#cxThreadComposer textarea')?.focus();
        }).catch(() => alert('That thread could not be opened.'));
    }
    document.getElementById('cxThreadClose')?.addEventListener('click', () => { shell.classList.remove('has-thread'); thread.id = 0; });
    if (@json($openThread ?? null)) openThread(@json($openThread ?? null));

    // ── Message actions (delegated, so new messages work too) ──────────
    function actions(container, inThread) {
        container?.addEventListener('click', e => {
            const msg = e.target.closest('.cx-msg'); if (!msg) return;
            const id = msg.dataset.id;
            const react = e.target.closest('.cx-react');
            const opt = e.target.closest('.cx-poll-opt');
            if (e.target.closest('.cx-react-add')) {
                emojiPicker(e.target.closest('.cx-react-add'), emoji => post(msgUrl + id + '/react', { emoji }).then(r => r.ok ? poll(true) : r.json().then(j => alert(j.message))));
            } else if (opt) {
                if (opt.disabled) return;
                const box = opt.closest('.cx-poll'), i = +opt.dataset.option;
                let picked = [...box.querySelectorAll('.cx-poll-opt.me')].map(b => +b.dataset.option);
                if (box.dataset.multiple === '1') picked = picked.includes(i) ? picked.filter(x => x !== i) : picked.concat(i);
                else picked = picked.includes(i) ? [] : [i];
                opt.classList.toggle('me');
                post(msgUrl + id + '/vote', { options: picked }).then(r => r.ok ? poll(true) : r.json().then(j => { alert(j.message || 'Your vote was not saved.'); poll(true); }));
            } else if (e.target.closest('.cx-remind')) {
                remindChooser(msg.querySelector('.cx-bubble') || msg, id);
            } else if (e.target.closest('.cx-poll-close')) {
                if (confirm('Close this poll? No one will be able to vote after that.')) post(msgUrl + id + '/close-poll').then(() => poll(true));
            } else if (react) {
                post(msgUrl + id + '/react', { emoji: react.dataset.emoji }).then(() => poll(true));
            } else if (e.target.closest('.cx-thread-open')) {
                openThread(id);
            } else if (e.target.closest('.cx-reply')) {
                if (inThread) { document.querySelector('#cxThreadComposer textarea')?.focus(); return; }
                const b = e.target.closest('.cx-reply');
                replyTo.value = id; replying.classList.remove('d-none');
                replying.querySelector('span').textContent = 'Replying to ' + (b.dataset.name || '') + ': ' + b.dataset.preview;
                document.getElementById('cxBody').focus();
            } else if (e.target.closest('.cx-pin')) {
                post(msgUrl + id + '/pin').then(() => poll(true));
            } else if (e.target.closest('.cx-save')) {
                post(msgUrl + id + '/save').then(() => poll(true));
            } else if (e.target.closest('.cx-edit')) {
                const text = prompt('Edit message', msg.dataset.body || '');
                if (text !== null) fetch(msgUrl + id, { method: 'PUT', headers: { ...headers, 'Content-Type': 'application/json' }, body: JSON.stringify({ body: text }) }).then(() => poll(true));
            } else if (e.target.closest('.cx-delete')) {
                if (confirm('Delete this message for everyone?')) fetch(msgUrl + id, { method: 'DELETE', headers }).then(() => poll(true));
            }
        });
    }
    actions(list, false);
    actions(tList, true);
    actions(tRoot, true);

    // ── Emoji picker (reactions and the composer) ──────────────────────
    const EMOJI = @json(\App\Models\Connect\Reaction::PICKER);
    let pop = null, popPick = null;
    function closePicker() { if (pop) { pop.remove(); pop = null; document.removeEventListener('mousedown', outside, true); } }
    function outside(e) { if (pop && !pop.contains(e.target)) closePicker(); }
    function emojiPicker(anchor, pick) {
        closePicker();
        popPick = pick;
        pop = document.createElement('div');
        pop.id = 'cxEmojiPop';
        pop.setAttribute('role', 'dialog');
        pop.setAttribute('aria-label', 'Emoji');
        const groups = Object.keys(EMOJI);
        pop.innerHTML = '<div class="cx-em-tabs">' + groups.map((g, i) => '<button type="button" data-g="' + i + '" class="' + (i ? '' : 'active') + '">' + g + '</button>').join('') + '</div><div class="cx-em-grid"></div>';
        const grid = pop.querySelector('.cx-em-grid');
        const show = i => { grid.innerHTML = EMOJI[groups[i]].map(e => '<button type="button" data-e="' + e + '" aria-label="' + e + '">' + e + '</button>').join(''); pop.querySelectorAll('.cx-em-tabs button').forEach(b => b.classList.toggle('active', +b.dataset.g === i)); };
        show(0);
        pop.addEventListener('click', e => {
            const t = e.target.closest('[data-g]'); if (t) return show(+t.dataset.g);
            const b = e.target.closest('[data-e]'); if (b) { const f = popPick; closePicker(); f(b.dataset.e); }
        });
        pop.addEventListener('keydown', e => { if (e.key === 'Escape') { closePicker(); anchor.focus(); } });
        document.body.appendChild(pop);
        const r = anchor.getBoundingClientRect(), w = pop.offsetWidth, h = pop.offsetHeight;
        pop.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left)) + 'px';
        pop.style.top = (r.top - h - 6 > 8 ? r.top - h - 6 : Math.min(window.innerHeight - h - 8, r.bottom + 6)) + 'px';
        setTimeout(() => document.addEventListener('mousedown', outside, true));
        grid.querySelector('button')?.focus();
    }

    const body = document.getElementById('cxBody');
    document.getElementById('cxEmojiBtn')?.addEventListener('click', e => emojiPicker(e.currentTarget, emoji => {
        const a = body.selectionStart ?? body.value.length, b = body.selectionEnd ?? a;
        body.value = body.value.slice(0, a) + emoji + body.value.slice(b);
        body.focus(); body.setSelectionRange(a + emoji.length, a + emoji.length);
        body.dispatchEvent(new Event('input'));
    }));

    // ── GIFs and polls (panels above the main composer) ────────────────
    const gifPanel = document.getElementById('cxGifPanel'), pollPanel = document.getElementById('cxPollPanel'), schedPanel = document.getElementById('cxSchedPanel');
    function panel(which) {
        document.getElementById('cxSchedBtn')?.setAttribute('aria-expanded', !!which && which === schedPanel && schedPanel.classList.contains('d-none'));
        [gifPanel, pollPanel, schedPanel].forEach(p => { if (p) p.classList.toggle('d-none', p !== which || !p.classList.contains('d-none')); });
        document.getElementById('cxGifBtn')?.setAttribute('aria-expanded', gifPanel && !gifPanel.classList.contains('d-none'));
        document.getElementById('cxPollBtn')?.setAttribute('aria-expanded', pollPanel && !pollPanel.classList.contains('d-none'));
    }
    document.querySelectorAll('.cx-panel-close').forEach(b => b.addEventListener('click', () => panel(null)));

    const sendExtra = data => {
        const fd = new FormData();
        fd.append('_token', token);
        if (replyTo?.value) fd.append('reply_to_id', replyTo.value);
        Object.entries(data).forEach(([k, v]) => Array.isArray(v) ? v.forEach(x => fd.append(k + '[]', x)) : fd.append(k, v));
        return fetch(form.action, { method: 'POST', headers, body: fd }).then(r => r.ok ? r.json() : r.json().then(j => Promise.reject(j)))
            .then(() => { replyTo.value = ''; replying.classList.add('d-none'); poll(true); setTimeout(() => toBottom(scroll), 300); });
    };

    const gifGrid = document.getElementById('cxGifGrid'), gifQ = document.getElementById('cxGifQ');
    const gifUrl = @json(route('connect.gifs'));
    let gifTimer, gifSeq = 0;
    function loadGifs() {
        const mine = ++gifSeq;
        gifGrid.innerHTML = '<div class="small text-muted p-2">Loading…</div>';
        fetch(gifUrl + '?q=' + encodeURIComponent(gifQ.value.trim()), { headers }).then(r => r.json()).then(data => {
            if (mine !== gifSeq) return;
            if (!data.enabled) { gifGrid.innerHTML = '<div class="small text-muted p-2">GIFs are not set up yet. An administrator can add a free GIPHY key under Settings → Integrations.</div>'; return; }
            if (!data.results.length) { gifGrid.innerHTML = '<div class="small text-muted p-2">No GIFs found.</div>'; return; }
            gifGrid.innerHTML = '';
            data.results.forEach(g => {
                const b = document.createElement('button'); b.type = 'button'; b.title = g.title || 'GIF';
                const img = document.createElement('img'); img.src = g.preview; img.alt = g.title || 'GIF'; img.loading = 'lazy'; img.referrerPolicy = 'no-referrer';
                b.appendChild(img);
                b.addEventListener('click', () => {
                    b.disabled = true;
                    sendExtra({ gif_url: g.url, gif_width: g.width, gif_height: g.height, gif_title: g.title || '' }).then(() => panel(null)).catch(j => alert((j && j.message) || 'The GIF was not sent.')).finally(() => b.disabled = false);
                });
                gifGrid.appendChild(b);
            });
        }).catch(() => { gifGrid.innerHTML = '<div class="small text-muted p-2">Could not load GIFs.</div>'; });
    }
    document.getElementById('cxGifBtn')?.addEventListener('click', () => { panel(gifPanel); if (!gifPanel.classList.contains('d-none')) { gifQ.focus(); if (!gifGrid.children.length) loadGifs(); } });
    gifQ?.addEventListener('input', () => { clearTimeout(gifTimer); gifTimer = setTimeout(loadGifs, 350); });

    const pollOpts = document.getElementById('cxPollOpts');
    document.getElementById('cxPollBtn')?.addEventListener('click', () => { panel(pollPanel); if (!pollPanel.classList.contains('d-none')) document.getElementById('cxPollQ').focus(); });
    document.getElementById('cxPollAdd')?.addEventListener('click', () => {
        const n = pollOpts.querySelectorAll('input').length;
        if (n >= 10) return;
        const i = document.createElement('input');
        i.className = 'form-control form-control-sm mb-1 cx-poll-in'; i.maxLength = 120; i.placeholder = 'Option ' + (n + 1); i.setAttribute('aria-label', 'Option ' + (n + 1));
        pollOpts.appendChild(i); i.focus();
        if (n + 1 >= 10) document.getElementById('cxPollAdd').disabled = true;
    });
    document.getElementById('cxPollSend')?.addEventListener('click', e => {
        const q = document.getElementById('cxPollQ').value.trim();
        const opts = [...pollOpts.querySelectorAll('input')].map(i => i.value.trim()).filter(Boolean);
        if (!q || opts.length < 2) { alert('Write a question and at least two options.'); return; }
        e.currentTarget.disabled = true;
        sendExtra({ poll_question: q, poll_options: opts, poll_multiple: document.getElementById('cxPollMulti').checked ? 1 : 0 })
            .then(() => {
                document.getElementById('cxPollQ').value = ''; document.getElementById('cxPollMulti').checked = false;
                pollOpts.querySelectorAll('input').forEach((i, n) => n < 2 ? i.value = '' : i.remove());
                document.getElementById('cxPollAdd').disabled = false;
                panel(null);
            })
            .catch(j => alert((j && j.message) || 'The poll was not posted.'))
            .finally(() => e.currentTarget && (document.getElementById('cxPollSend').disabled = false));
    });

    // ── Send later and reminders ───────────────────────────────────────
    const scheduleUrl = @json($conversation ? route('connect.schedule', $conversation) : '');
    const remindUrl = @json(route('connect.reminders.store'));
    const say = (text, kind) => window.Live ? Live.toast(text, kind) : alert(text);
    function schedule(when, at) {
        const text = body.value.trim();
        if (!text) { say('Write the message first, then choose when to send it.', 'danger'); body.focus(); return; }
        post(scheduleUrl, { body: text, when: when || '', at: at || '', reply_to_id: replyTo?.value || '' })
            .then(r => r.json().then(j => r.ok ? j : Promise.reject(j)))
            .then(j => { body.value = ''; body.dispatchEvent(new Event('input')); replyTo.value = ''; replying.classList.add('d-none'); panel(null); say(j.message); })
            .catch(j => say((j && j.message) || 'It was not scheduled.', 'danger'));
    }
    document.getElementById('cxSchedBtn')?.addEventListener('click', () => panel(schedPanel));
    schedPanel?.querySelectorAll('.cx-sched-opt').forEach(b => b.addEventListener('click', () => schedule(b.dataset.when)));
    document.getElementById('cxSchedGo')?.addEventListener('click', () => {
        const at = document.getElementById('cxSchedAt').value;
        if (!at) { say('Pick a date and time.', 'danger'); return; }
        schedule('', at);
    });

    let remindPop = null;
    function remindChooser(anchor, messageId) {
        remindPop?.remove();
        remindPop = document.createElement('div');
        remindPop.id = 'cxEmojiPop';
        remindPop.setAttribute('role', 'dialog');
        remindPop.setAttribute('aria-label', 'Remind me');
        remindPop.innerHTML = '<div class="fw-semibold small mb-2"><i class="ri-alarm-line"></i> Remind me about this</div>'
            + @js(collect(\App\Services\Connect\LaterService::CHOICES)->map(fn ($v, $k) => '<button type="button" class="cx-sched-opt mb-1 me-1" data-when="' . $k . '">' . e($v) . '</button>')->implode(''))
            + '<div class="d-flex gap-2 mt-1"><input type="datetime-local" class="form-control form-control-sm" aria-label="Pick a time"><button type="button" class="btn btn-sm btn-primary">Set</button></div>';
        const go = (when, at) => {
            post(remindUrl, { message_id: messageId, when: when || '', at: at || '' }).then(r => r.json().then(j => r.ok ? j : Promise.reject(j)))
                .then(j => { say(j.message); remindPop?.remove(); remindPop = null; }).catch(j => say((j && j.message) || 'The reminder was not set.', 'danger'));
        };
        remindPop.querySelectorAll('[data-when]').forEach(b => b.addEventListener('click', () => go(b.dataset.when)));
        remindPop.querySelector('.btn-primary').addEventListener('click', () => { const v = remindPop.querySelector('input').value; v ? go('', v) : say('Pick a time.', 'danger'); });
        document.body.appendChild(remindPop);
        const r = anchor.getBoundingClientRect(), w = remindPop.offsetWidth, h = remindPop.offsetHeight;
        remindPop.style.left = Math.max(8, Math.min(window.innerWidth - w - 8, r.left)) + 'px';
        remindPop.style.top = (r.top - h - 6 > 8 ? r.top - h - 6 : Math.min(window.innerHeight - h - 8, r.bottom + 6)) + 'px';
        const outside = e => { if (remindPop && !remindPop.contains(e.target)) { remindPop.remove(); remindPop = null; document.removeEventListener('mousedown', outside, true); } };
        setTimeout(() => document.addEventListener('mousedown', outside, true));
        remindPop.querySelector('button').focus();
    }

    const older = document.getElementById('cxOlder');
    if (older) older.addEventListener('click', () => {
        const first = list.querySelector(':scope > [data-id]');
        fetch(@json($conversation ? route('connect.older', $conversation) : '') + '?before=' + first.dataset.id, { headers }).then(r => r.json()).then(data => {
            const h = scroll.scrollHeight;
            list.insertAdjacentHTML('afterbegin', data.html);
            scroll.scrollTop = scroll.scrollHeight - h;
            if (!data.more) older.remove();
        });
    });
})();
</script>
@endpush
