@extends('layouts.master')
@include('modules.drive._styles')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
@php
    use App\Models\Drive\File;
    $here = ['parent_id' => $folder?->id, 'project_id' => $project?->id];
    $empty = $folders->isEmpty() && $files->isEmpty();
    $where = function ($item) {
        if ($item->project_id) return $item->project?->name ?? 'Project';
        $parent = $item instanceof \App\Models\Drive\Folder ? $item->parent : $item->folder;
        return $parent?->name ?? ($item->owner_id === auth()->id() ? 'My Drive' : $item->owner?->name);
    };
@endphp

    <x-cb.hero :title="$title" :icon="$icon" :subtitle="$subtitle">
        @if($trail || $project)
            <x-slot:pills>
                @if($folder && $folder->project_id)
                    <a href="{{ route('drive.project', $folder->project_id) }}" class="cb-meta-pill"><i class="ri-folder-chart-line"></i>{{ $folder->project?->name }}</a>
                @elseif($folder && $folder->owner_id === auth()->id())
                    <a href="{{ route('drive.index') }}" class="cb-meta-pill"><i class="ri-hard-drive-2-line"></i>My Drive</a>
                @elseif($folder)
                    <a href="{{ route('drive.shared') }}" class="cb-meta-pill"><i class="ri-share-line"></i>Shared with me</a>
                @endif
                @foreach(array_slice($trail, 0, -1) as $t)
                    <a href="{{ route('drive.folder', $t) }}" class="cb-meta-pill"><i class="ri-arrow-right-s-line"></i>{{ $t->name }}</a>
                @endforeach
                @if($folder && $level === 'viewer')<span class="cb-meta-pill"><i class="ri-eye-line"></i>View only</span>@endif
            </x-slot:pills>
        @endif
        <x-slot:actions>
            @if($folder)
                <a href="{{ route('drive.share', ['type' => 'folder', 'id' => $folder->id]) }}" class="cb-hero-btn"><i class="ri-user-add-line"></i>Share</a>
                <a href="{{ route('drive.folder.zip', $folder) }}" class="cb-hero-btn"><i class="ri-download-2-line"></i>Download</a>
            @endif
            @if($project && \Illuminate\Support\Facades\Route::has('projects.show'))
                <a href="{{ route('projects.show', $project) }}" class="cb-hero-btn"><i class="ri-folder-chart-line"></i>Project</a>
            @endif
            @if($inTrash && ($folders->isNotEmpty() || $files->isNotEmpty()))
                <form method="POST" action="{{ route('drive.trash.empty') }}" onsubmit="return confirm('Delete everything you own in the trash for good?')">@csrf<button class="cb-hero-btn"><i class="ri-delete-bin-line"></i>Empty trash</button></form>
            @endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-3">@include('modules.drive._side')</div>

        <div class="col-lg-9">
            <div class="cb-card drive-drop" id="driveDrop">
                <div class="cb-card-header flex-wrap gap-2">
                    <div class="d-flex gap-2 flex-wrap">
                        @if($canAdd)
                            <label class="btn btn-sm btn-primary mb-0"><i class="ri-upload-cloud-2-line me-1"></i>Upload
                                <input type="file" multiple hidden id="driveFiles">
                            </label>
                            <button type="button" class="btn btn-sm btn-soft-primary" data-bs-toggle="collapse" data-bs-target="#newFolder" aria-expanded="false"><i class="ri-folder-add-line me-1"></i>New folder</button>
                        @endif
                    </div>
                    <div class="d-flex gap-2 align-items-center">
                        <select class="form-select form-select-sm" style="width:auto" aria-label="Sort by" onchange="location.href=this.value">
                            @foreach(['name' => 'Name', 'updated' => 'Last changed', 'size' => 'Size'] as $k => $label)
                                <option value="{{ request()->fullUrlWithQuery(['sort' => $k]) }}" @selected($sort === $k)>{{ $label }}</option>
                            @endforeach
                        </select>
                        <div class="btn-group btn-group-sm" role="group" aria-label="Layout">
                            <a href="{{ request()->fullUrlWithQuery(['view' => 'list']) }}" class="btn {{ $grid ? 'btn-light' : 'btn-soft-primary' }}" title="List" aria-label="List"><i class="ri-list-check"></i></a>
                            <a href="{{ request()->fullUrlWithQuery(['view' => 'grid']) }}" class="btn {{ $grid ? 'btn-soft-primary' : 'btn-light' }}" title="Grid" aria-label="Grid"><i class="ri-layout-grid-line"></i></a>
                        </div>
                    </div>
                </div>

                @if($canAdd)
                    <div class="collapse" id="newFolder">
                        <form method="POST" action="{{ route('drive.folders.store') }}" class="d-flex gap-2 p-3 border-bottom">
                            @csrf
                            @foreach($here as $k => $v)@if($v)<input type="hidden" name="{{ $k }}" value="{{ $v }}">@endif @endforeach
                            <input name="name" class="form-control form-control-sm" placeholder="Folder name" maxlength="150" required aria-label="Folder name">
                            <button class="btn btn-sm btn-primary">Create</button>
                        </form>
                    </div>
                    <div id="driveProgress" class="px-3 pt-2 d-none">
                        <div class="small text-muted mb-1" id="driveProgressText">Uploading…</div>
                        <div class="progress" style="height:6px"><div class="progress-bar" id="driveProgressBar" style="width:0%"></div></div>
                    </div>
                @endif

                @if($empty)
                    <div class="empty-state py-5">
                        <i class="{{ $inTrash ? 'ri-delete-bin-6-line' : 'ri-folder-open-line' }}"></i>
                        <h6>{{ $inTrash ? 'The trash is empty' : ($section === 'search' ? 'Nothing matches' : 'Nothing here yet') }}</h6>
                        @if($canAdd)<p class="text-muted small mb-0">Drop files here, or use Upload.</p>@endif
                    </div>
                @elseif($grid)
                    <div class="drive-grid">
                        @foreach($folders as $item)
                            <div class="drive-tile">
                                <a href="{{ $inTrash ? '#' : route('drive.folder', $item) }}" class="text-reset"><div class="thumb"><i class="ri-folder-3-fill text-warning"></i></div></a>
                                <a href="{{ $inTrash ? '#' : route('drive.folder', $item) }}" class="name" title="{{ $item->name }}">{{ $item->name }}</a>
                                <div class="small text-muted text-truncate">{{ $item->project_id ? 'Project folder' : $item->owner?->name }}</div>
                                @include('modules.drive._menu')
                            </div>
                        @endforeach
                        @foreach($files as $item)
                            <div class="drive-tile">
                                <a href="{{ route('drive.file', $item) }}" class="text-reset"><div class="thumb">
                                    @if($item->previewKind() === 'image' && !$inTrash)<img src="{{ route('drive.file.view', $item) }}" alt="" loading="lazy">@else<i class="{{ $item->icon() }}"></i>@endif
                                </div></a>
                                <a href="{{ route('drive.file', $item) }}" class="name" title="{{ $item->name }}">@if(isset($stars['file:' . $item->id]))<i class="ri-star-fill drive-star me-1"></i>@endif{{ $item->name }}</a>
                                <div class="small text-muted">{{ File::humanSize($item->size) }}</div>
                                @include('modules.drive._menu')
                            </div>
                        @endforeach
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover drive-table mb-0">
                            <thead class="table-light"><tr>
                                <th>Name</th>
                                <th class="d-none d-md-table-cell">{{ $showShare ? 'Shared by' : ($showWhere ? 'Location' : 'Owner') }}</th>
                                <th class="d-none d-sm-table-cell">{{ $inTrash ? 'Deleted' : 'Last changed' }}</th>
                                <th class="d-none d-sm-table-cell text-end">Size</th>
                                <th style="width:48px"><span class="visually-hidden">Actions</span></th>
                            </tr></thead>
                            <tbody>
                                @foreach($folders as $item)
                                    <tr>
                                        <td style="max-width:0;width:50%"><div class="drive-name">
                                            <i class="ri-folder-3-fill text-warning ico"></i>
                                            @if($inTrash)<span class="text-truncate">{{ $item->name }}</span>@else<a href="{{ route('drive.folder', $item) }}" title="{{ $item->name }}">{{ $item->name }}</a>@endif
                                            @if(isset($stars['folder:' . $item->id]))<i class="ri-star-fill drive-star" title="Starred"></i>@endif
                                        </div></td>
                                        <td class="d-none d-md-table-cell small text-muted">{{ $showShare ? ($item->share?->sharer?->name ?? $item->owner?->name) : ($showWhere ? $where($item) : ($item->owner_id === auth()->id() ? 'me' : $item->owner?->name)) }}</td>
                                        <td class="d-none d-sm-table-cell small text-muted">{{ ($inTrash ? $item->trashed_at : $item->updated_at)?->diffForHumans() }}</td>
                                        <td class="d-none d-sm-table-cell small text-muted text-end">—</td>
                                        <td>@include('modules.drive._menu')</td>
                                    </tr>
                                @endforeach
                                @foreach($files as $item)
                                    <tr>
                                        <td style="max-width:0;width:50%"><div class="drive-name">
                                            <i class="{{ $item->icon() }} ico"></i>
                                            <a href="{{ route('drive.file', $item) }}" title="{{ $item->name }}">{{ $item->name }}</a>
                                            @if(isset($stars['file:' . $item->id]))<i class="ri-star-fill drive-star" title="Starred"></i>@endif
                                            @if($item->version > 1)<span class="badge bg-light text-muted">v{{ $item->version }}</span>@endif
                                        </div></td>
                                        <td class="d-none d-md-table-cell small text-muted">{{ $showShare ? ($item->share?->sharer?->name ?? $item->owner?->name) : ($showWhere ? $where($item) : ($item->owner_id === auth()->id() ? 'me' : $item->owner?->name)) }}</td>
                                        <td class="d-none d-sm-table-cell small text-muted">{{ ($inTrash ? $item->trashed_at : $item->updated_at)?->diffForHumans() }}@if(!$inTrash && $item->editor && $item->updated_by !== auth()->id()) · {{ $item->editor->name }}@endif</td>
                                        <td class="d-none d-sm-table-cell small text-muted text-end text-nowrap">{{ File::humanSize($item->size) }}</td>
                                        <td>@include('modules.drive._menu')</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        </div>
    </div>

    @include('modules.drive._rename')
</div></div></div>
@endsection

@if($canAdd)
@push('scripts')
<script>
(function () {
    const drop = document.getElementById('driveDrop'), input = document.getElementById('driveFiles');
    const box = document.getElementById('driveProgress'), bar = document.getElementById('driveProgressBar'), text = document.getElementById('driveProgressText');
    const here = @json(array_filter($here));
    const maxBytes = {{ (int) config('qudroid.drive.max_upload_mb', 200) * 1024 * 1024 }};

    function send(files) {
        files = Array.from(files || []);
        if (!files.length) return;
        const big = files.find(f => f.size > maxBytes);
        if (big) { alert(big.name + ' is too big to upload.'); return; }
        const data = new FormData();
        files.forEach(f => data.append('files[]', f));
        Object.entries(here).forEach(([k, v]) => data.append(k, v));
        data.append('_token', document.querySelector('meta[name=csrf-token]')?.content || @json(csrf_token()));

        const xhr = new XMLHttpRequest();
        xhr.open('POST', @json(route('drive.upload')));
        xhr.setRequestHeader('Accept', 'application/json');
        box.classList.remove('d-none');
        text.textContent = 'Uploading ' + (files.length === 1 ? files[0].name : files.length + ' files') + '…';
        xhr.upload.onprogress = e => { if (e.lengthComputable) bar.style.width = Math.round(e.loaded / e.total * 100) + '%'; };
        xhr.onload = () => {
            if (xhr.status >= 200 && xhr.status < 300) { location.reload(); return; }
            let msg = 'The upload failed.';
            try { const j = JSON.parse(xhr.responseText); msg = j.errors ? Object.values(j.errors).flat()[0] : (j.message || msg); } catch (e) {}
            text.textContent = msg; text.classList.add('text-danger'); bar.classList.add('bg-danger');
        };
        xhr.onerror = () => { text.textContent = 'The upload failed. Check your connection.'; text.classList.add('text-danger'); };
        xhr.send(data);
    }

    input?.addEventListener('change', () => send(input.files));
    ['dragenter', 'dragover'].forEach(ev => drop.addEventListener(ev, e => { if (e.dataTransfer?.types?.includes('Files')) { e.preventDefault(); drop.classList.add('over'); } }));
    ['dragleave', 'drop'].forEach(ev => drop.addEventListener(ev, e => { if (ev === 'dragleave' && drop.contains(e.relatedTarget)) return; drop.classList.remove('over'); }));
    drop.addEventListener('drop', e => { if (e.dataTransfer?.files?.length) { e.preventDefault(); send(e.dataTransfer.files); } });
})();
</script>
@endpush
@endif
