@extends('layouts.master')
@include('modules.drive._styles')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
@php
    $isFolder = $item instanceof \App\Models\Drive\Folder;
    $key = ['type' => $item::TYPE, 'id' => $item->id];
    $canEdit = \App\Services\Drive\DriveService::RANK[$level] >= 2;
@endphp

    <x-cb.hero :title="'Share ' . $item->name" icon="ri-user-add-line" :back="$back" back-label="Back"
        :subtitle="$isFolder ? 'People get access to this folder and everything in it.' : 'Choose who can open this file.'" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-7">
            <x-cb.card title="People with access" icon="ri-team-line" :flush="true">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center">
                        <span>{{ $item->owner->name }} @if($item->owner_id === auth()->id())<span class="text-muted small">(you)</span>@endif</span>
                        <span class="small text-muted">Owner</span>
                    </li>
                    @if($item->project_id)
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <span><i class="ri-folder-chart-line me-1"></i>Everyone on the project</span>
                            <span class="small text-muted">By project role</span>
                        </li>
                    @endif
                    @foreach($shares as $s)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <span>{{ $s->user->name }} <span class="small text-muted">{{ $s->user->email }}</span></span>
                            <span class="d-flex align-items-center gap-2">
                                @if($canEdit)
                                    <form method="POST" action="{{ route('drive.share.store', $key) }}" class="d-flex">
                                        @csrf
                                        <input type="hidden" name="users[]" value="{{ $s->user_id }}">
                                        <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" aria-label="Access for {{ $s->user->name }}">
                                            @foreach(\App\Models\Drive\Share::ROLES as $r => $label)<option value="{{ $r }}" @selected($s->role === $r)>{{ $label }}</option>@endforeach
                                        </select>
                                    </form>
                                @else
                                    <span class="small text-muted">{{ \App\Models\Drive\Share::ROLES[$s->role] }}</span>
                                @endif
                                @if($canEdit || $s->user_id === auth()->id())
                                    <form method="POST" action="{{ route('drive.share.destroy', $key + ['user' => $s->user_id]) }}">@csrf @method('DELETE')
                                        <button class="btn btn-sm btn-ghost-danger btn-icon" title="Remove" aria-label="Remove {{ $s->user->name }}"><i class="ri-close-line"></i></button>
                                    </form>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-cb.card>

            @if($canEdit && $people->isNotEmpty())
                <x-cb.card title="Add people" icon="ri-user-add-line">
                    <form method="POST" action="{{ route('drive.share.store', $key) }}">
                        @csrf
                        <select name="users[]" class="form-select mb-2" multiple size="8" required aria-label="People">
                            @foreach($people as $p)<option value="{{ $p->id }}">{{ $p->name }} — {{ $p->email }}</option>@endforeach
                        </select>
                        <div class="form-text mb-2">Hold Ctrl (or ⌘) to pick several people.</div>
                        <div class="d-flex gap-2">
                            <select name="role" class="form-select" style="max-width:180px" aria-label="Access">
                                @foreach(\App\Models\Drive\Share::ROLES as $r => $label)<option value="{{ $r }}">{{ $label }}</option>@endforeach
                            </select>
                            <button class="btn btn-primary">Share</button>
                        </div>
                    </form>
                </x-cb.card>
            @endif
        </div>

        <div class="col-lg-5">
            <x-cb.card title="Anyone with the link" icon="ri-link">
                @if($link)
                    <div class="input-group input-group-sm mb-2">
                        <input class="form-control" id="shareLink" value="{{ $link->url() }}" readonly aria-label="Share link">
                        <button class="btn btn-soft-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('shareLink').value).then(() => { this.textContent = 'Copied'; })">Copy</button>
                    </div>
                    <p class="small text-muted mb-2">
                        Anyone with this link can view and download{{ $isFolder ? ' everything in this folder' : '' }}, without signing in.
                        {{ $link->expires_at ? 'It stops working ' . $link->expires_at->format('j M Y') . '.' : 'It doesn\'t expire.' }}
                        Downloaded {{ $link->downloads }} {{ str('time')->plural($link->downloads) }}.
                    </p>
                    @if($canEdit)
                        <form method="POST" action="{{ route('drive.link.destroy', $key) }}">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger">Turn off link</button></form>
                    @endif
                @elseif($canEdit)
                    <p class="small text-muted">Make a link for people outside the portal, such as clients. They can view and download only.</p>
                    <form method="POST" action="{{ route('drive.link.store', $key) }}" class="d-flex gap-2">
                        @csrf
                        <select name="days" class="form-select form-select-sm" aria-label="Link expiry">
                            <option value="7">Expires in 7 days</option>
                            <option value="1">Expires in 1 day</option>
                            <option value="30">Expires in 30 days</option>
                            <option value="90">Expires in 90 days</option>
                            <option value="">Never expires</option>
                        </select>
                        <button class="btn btn-sm btn-primary text-nowrap">Create link</button>
                    </form>
                @else
                    <p class="small text-muted mb-0">No link. Ask someone who can edit this to make one.</p>
                @endif
            </x-cb.card>

            @if($conversations->isNotEmpty())
                <x-cb.card title="Send in a chat" icon="ri-chat-3-line">
                    <form method="POST" action="{{ route('drive.send', $key) }}">
                        @csrf
                        <select name="conversation_id" class="form-select form-select-sm mb-2" required aria-label="Conversation">
                            <option value="">Choose a chat…</option>
                            @foreach($conversations as $c)<option value="{{ $c->id }}">{{ $c->titleFor(auth()->user()) }}</option>@endforeach
                        </select>
                        <input name="note" class="form-control form-control-sm mb-2" maxlength="1000" placeholder="Add a message (optional)" aria-label="Message">
                        <button class="btn btn-sm btn-primary w-100"><i class="ri-send-plane-line me-1"></i>Send</button>
                    </form>
                </x-cb.card>
            @endif
        </div>
    </div>
</div></div></div>
@endsection
