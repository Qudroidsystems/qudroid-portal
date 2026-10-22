@extends('layouts.master')
@include('modules.drive._styles')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
@php
    use App\Models\Drive\File;
    $key = ['type' => 'file', 'id' => $file->id];
    $canEdit = \App\Services\Drive\DriveService::RANK[$level] >= 2;
    $kind = $file->previewKind();
@endphp

    <x-cb.hero :title="$file->name" :icon="trim(explode(' ', $file->icon())[0])" :back="$back" back-label="Back">
        <x-slot:pills>
            <span class="cb-meta-pill"><i class="ri-hard-drive-2-line"></i>{{ File::humanSize($file->size) }}</span>
            @if($file->version > 1)<span class="cb-meta-pill"><i class="ri-history-line"></i>Version {{ $file->version }}</span>@endif
            @if($file->project)<a href="{{ route('drive.project', $file->project) }}" class="cb-meta-pill"><i class="ri-folder-chart-line"></i>{{ $file->project->name }}</a>@endif
            @foreach($trail as $t)<a href="{{ route('drive.folder', $t) }}" class="cb-meta-pill"><i class="ri-folder-3-line"></i>{{ $t->name }}</a>@endforeach
            @if(!$canEdit)<span class="cb-meta-pill"><i class="ri-eye-line"></i>View only</span>@endif
            @if($trashed)<span class="cb-meta-pill"><i class="ri-delete-bin-6-line"></i>In the trash</span>@endif
        </x-slot:pills>
        <x-slot:actions>
            @if($trashed)
                <form method="POST" action="{{ route('drive.restore', $key) }}">@csrf<button class="cb-hero-btn"><i class="ri-arrow-go-back-line"></i>Restore</button></form>
            @else
                <a href="{{ route('drive.file.download', $file) }}" class="cb-hero-btn"><i class="ri-download-2-line"></i>Download</a>
                <a href="{{ route('drive.share', $key) }}" class="cb-hero-btn"><i class="ri-user-add-line"></i>Share</a>
                <form method="POST" action="{{ route('drive.star', $key) }}">@csrf<button class="cb-hero-btn" aria-label="{{ $starred ? 'Remove star' : 'Star' }}"><i class="{{ $starred ? 'ri-star-fill' : 'ri-star-line' }}"></i>{{ $starred ? 'Starred' : 'Star' }}</button></form>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <div class="cb-card"><div class="cb-card-body">
                <div class="drive-preview">
                    @if($trashed || !$kind)
                        <div class="text-center p-5">
                            <i class="{{ $file->icon() }}" style="font-size:64px"></i>
                            <p class="text-muted mb-3">{{ $trashed ? 'Restore this file to open it.' : 'There\'s no preview for this kind of file.' }}</p>
                            @unless($trashed)<a href="{{ route('drive.file.download', $file) }}" class="btn btn-primary"><i class="ri-download-2-line me-1"></i>Download</a>@endunless
                        </div>
                    @elseif($kind === 'image')
                        <img src="{{ route('drive.file.view', $file) }}?v={{ $file->version }}" alt="{{ $file->name }}">
                    @elseif($kind === 'pdf')
                        <iframe src="{{ route('drive.file.view', $file) }}?v={{ $file->version }}" title="{{ $file->name }}"></iframe>
                    @elseif($kind === 'video')
                        <video src="{{ route('drive.file.view', $file) }}?v={{ $file->version }}" controls preload="metadata"></video>
                    @elseif($kind === 'audio')
                        <audio src="{{ route('drive.file.view', $file) }}?v={{ $file->version }}" controls class="w-75 m-5"></audio>
                    @elseif($kind === 'text')
                        <pre>{{ $text }}</pre>
                    @endif
                </div>
            </div></div>

            <x-cb.card title="Versions" icon="ri-history-line" :count="$file->versions->count() + 1" :flush="true">
                @if($canEdit && !$trashed)
                    <x-slot:tools>
                        <form method="POST" action="{{ route('drive.file.versions.store', $file) }}" enctype="multipart/form-data" id="versionForm">
                            @csrf
                            <label class="btn btn-sm btn-soft-primary mb-0"><i class="ri-upload-2-line me-1"></i>Upload new version
                                <input type="file" name="file" hidden onchange="this.form.submit()">
                            </label>
                        </form>
                    </x-slot:tools>
                @endif
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                        <span><span class="badge bg-primary-subtle text-primary me-2">v{{ $file->version }} · current</span>
                            <span class="small text-muted">{{ $file->editor?->name ?? $file->owner->name }} · {{ $file->updated_at->format('j M Y, H:i') }} · {{ File::humanSize($file->size) }}</span></span>
                    </li>
                    @foreach($file->versions as $v)
                        <li class="list-group-item d-flex justify-content-between align-items-center gap-2 flex-wrap">
                            <span><span class="badge bg-light text-muted me-2">v{{ $v->version }}</span>
                                <span class="small text-muted">{{ $v->uploader?->name }} · {{ $v->created_at?->format('j M Y, H:i') }} · {{ File::humanSize($v->size) }}</span></span>
                            <span class="d-flex gap-1">
                                <a href="{{ route('drive.file.download', [$file, 'version' => $v->id]) }}" class="btn btn-sm btn-ghost-secondary" title="Download version {{ $v->version }}" aria-label="Download version {{ $v->version }}"><i class="ri-download-2-line"></i></a>
                                @if($canEdit && !$trashed)
                                    <form method="POST" action="{{ route('drive.file.versions.restore', [$file, $v]) }}">@csrf<button class="btn btn-sm btn-soft-secondary">Make current</button></form>
                                @endif
                            </span>
                        </li>
                    @endforeach
                </ul>
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            <x-cb.card title="Details" icon="ri-information-line">
                <dl class="row small mb-0">
                    <dt class="col-5 text-muted fw-normal">Owner</dt><dd class="col-7">{{ $file->owner->name }}</dd>
                    <dt class="col-5 text-muted fw-normal">Added</dt><dd class="col-7">{{ $file->created_at->format('j M Y, H:i') }}</dd>
                    <dt class="col-5 text-muted fw-normal">Last changed</dt><dd class="col-7">{{ $file->updated_at->diffForHumans() }}@if($file->editor) by {{ $file->editor->name }}@endif</dd>
                    <dt class="col-5 text-muted fw-normal">Type</dt><dd class="col-7 text-break">{{ $file->mime ?: '—' }}</dd>
                    <dt class="col-5 text-muted fw-normal">Your access</dt><dd class="col-7">{{ ['owner' => 'Full', 'editor' => 'Can edit', 'viewer' => 'Can view'][$level] }}</dd>
                </dl>
                @if($canEdit && !$trashed)
                    <form method="POST" action="{{ route('drive.file.describe', $file) }}" class="mt-3">
                        @csrf
                        <label class="form-label small text-muted" for="desc">Description</label>
                        <textarea name="description" id="desc" rows="2" maxlength="500" class="form-control form-control-sm" placeholder="What is this file?">{{ $file->description }}</textarea>
                        <button class="btn btn-sm btn-light mt-2">Save</button>
                    </form>
                @elseif($file->description)
                    <p class="small mt-3 mb-0">{{ $file->description }}</p>
                @endif
            </x-cb.card>

            @unless($trashed)
                <x-cb.card title="Who has access" icon="ri-team-line" :flush="true">
                    <x-slot:tools><a href="{{ route('drive.share', $key) }}" class="btn btn-sm btn-soft-primary">Manage</a></x-slot:tools>
                    <ul class="list-group list-group-flush small">
                        <li class="list-group-item d-flex justify-content-between"><span>{{ $file->owner->name }}</span><span class="text-muted">Owner</span></li>
                        @if($file->project)<li class="list-group-item d-flex justify-content-between"><span>Everyone on {{ $file->project->name }}</span><span class="text-muted">Project</span></li>@endif
                        @foreach($shares as $s)
                            <li class="list-group-item d-flex justify-content-between"><span>{{ $s->user->name }}</span><span class="text-muted">{{ \App\Models\Drive\Share::ROLES[$s->role] }}</span></li>
                        @endforeach
                        @if($link)<li class="list-group-item d-flex justify-content-between"><span><i class="ri-link me-1"></i>Anyone with the link</span><span class="text-muted">Can view</span></li>@endif
                    </ul>
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
                            <div class="form-text">People in the chat who can't open it yet get view access.</div>
                        </form>
                    </x-cb.card>
                @endif

                @if($canEdit)
                    <x-cb.card title="More" icon="ri-settings-3-line">
                        <div class="d-flex flex-wrap gap-2">
                            <button type="button" class="btn btn-sm btn-light" data-drive-rename="{{ route('drive.rename', $key) }}" data-name="{{ $file->name }}"><i class="ri-edit-line me-1"></i>Rename</button>
                            <a href="{{ route('drive.move', $key) }}" class="btn btn-sm btn-light"><i class="ri-folder-transfer-line me-1"></i>Move</a>
                            <form method="POST" action="{{ route('drive.trash.item', $key) }}">@csrf<button class="btn btn-sm btn-soft-danger"><i class="ri-delete-bin-6-line me-1"></i>Move to trash</button></form>
                        </div>
                    </x-cb.card>
                @endif
            @endunless
        </div>
    </div>

    @include('modules.drive._rename')
</div></div></div>
@endsection
