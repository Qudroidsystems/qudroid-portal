@extends('layouts.guest')
@section('title', $root->name)

@section('content')
@php
    use App\Models\Drive\File;
    $token = $link->token;
@endphp
<div class="apply-course p-4 p-md-5 mx-auto" style="max-width: 860px">
    @if($file)
        <div class="d-flex align-items-center gap-3 mb-3">
            <i class="{{ $file->icon() }}" style="font-size:40px"></i>
            <div class="min-w-0">
                <h4 class="fw-semibold mb-0 text-break">{{ $file->name }}</h4>
                <div class="text-muted small">{{ File::humanSize($file->size) }} · shared by {{ $file->owner?->name }}</div>
            </div>
        </div>
        @php $kind = $file->previewKind(); @endphp
        @if(in_array($kind, ['image', 'pdf', 'video', 'audio'], true))
            <div class="mb-3 text-center rounded overflow-hidden" style="background:#f3f3f9">
                @if($kind === 'image')<img src="{{ route('drive.public.file', [$token, $file, 'inline' => 1]) }}" alt="{{ $file->name }}" style="max-width:100%;max-height:70vh">
                @elseif($kind === 'pdf')<iframe src="{{ route('drive.public.file', [$token, $file, 'inline' => 1]) }}" title="{{ $file->name }}" style="width:100%;height:70vh;border:0"></iframe>
                @elseif($kind === 'video')<video src="{{ route('drive.public.file', [$token, $file, 'inline' => 1]) }}" controls style="max-width:100%;max-height:70vh"></video>
                @else<audio src="{{ route('drive.public.file', [$token, $file, 'inline' => 1]) }}" controls class="m-4"></audio>@endif
            </div>
        @endif
        <a href="{{ route('drive.public.file', [$token, $file]) }}" class="btn btn-primary"><i class="ri-download-2-line me-1"></i>Download</a>
    @else
        <div class="d-flex justify-content-between align-items-start gap-3 flex-wrap mb-3">
            <div>
                <h4 class="fw-semibold mb-1"><i class="ri-folder-3-fill text-warning me-1"></i>{{ $folder->name }}</h4>
                <div class="small text-muted">
                    @foreach($trail as $t)
                        @if(!$loop->last)<a href="{{ $t->id === $root->id ? route('drive.public', $token) : route('drive.public.folder', [$token, $t]) }}">{{ $t->name }}</a> / @endif
                    @endforeach
                    shared by {{ $root->owner?->name }}
                </div>
            </div>
            <a href="{{ route('drive.public.zip', [$token, 'folder' => $folder->id]) }}" class="btn btn-primary btn-sm"><i class="ri-download-2-line me-1"></i>Download all</a>
        </div>
        <div class="list-group">
            @forelse($folders as $f)
                <a href="{{ route('drive.public.folder', [$token, $f]) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2"><i class="ri-folder-3-fill text-warning fs-5"></i>{{ $f->name }}</a>
            @empty
            @endforelse
            @foreach($files as $f)
                <a href="{{ route('drive.public.file', [$token, $f]) }}" class="list-group-item list-group-item-action d-flex align-items-center gap-2">
                    <i class="{{ $f->icon() }} fs-5"></i><span class="text-truncate flex-grow-1">{{ $f->name }}</span><span class="small text-muted text-nowrap">{{ File::humanSize($f->size) }}</span>
                </a>
            @endforeach
            @if($folders->isEmpty() && $files->isEmpty())<div class="list-group-item text-muted text-center py-4">This folder is empty.</div>@endif
        </div>
    @endif
    <p class="small text-muted mt-4 mb-0">Shared from {{ $brand['company'] ?? 'Qudroid Systems' }}. @if($link->expires_at) This link stops working {{ $link->expires_at->format('j M Y') }}.@endif</p>
</div>
@endsection
