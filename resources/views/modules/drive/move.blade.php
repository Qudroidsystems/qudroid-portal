@extends('layouts.master')
@include('modules.drive._styles')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
@php
    $key = ['type' => $item::TYPE, 'id' => $item->id];
    $current = $item instanceof \App\Models\Drive\Folder ? $item->parent_id : $item->folder_id;
@endphp

    <x-cb.hero :title="'Move ' . $item->name" icon="ri-folder-transfer-line" :back="$back" back-label="Back"
        subtitle="Choose where it should go. Moving into a project folder shares it with the project." />

    @include('partials.flash')

    <x-cb.card :flush="true">
        <form method="POST" action="{{ route('drive.move.store', $key) }}">
            @csrf
            <div class="list-group list-group-flush" style="max-height:60vh;overflow:auto">
                @if($item->owner_id === auth()->id())
                    <label class="list-group-item d-flex gap-2 align-items-center">
                        <input class="form-check-input mt-0" type="radio" name="to" value="mine" @checked(!$current && !$item->project_id)>
                        <i class="ri-hard-drive-2-line text-primary"></i><span class="fw-semibold">My Drive</span>
                    </label>
                @endif
                @foreach($projects as $p)
                    <label class="list-group-item d-flex gap-2 align-items-center">
                        <input class="form-check-input mt-0" type="radio" name="to" value="project:{{ $p->id }}" @checked(!$current && $item->project_id === $p->id)>
                        <i class="ri-folder-chart-line text-primary"></i><span class="fw-semibold">{{ $p->name }}</span> <span class="small text-muted">project files</span>
                    </label>
                @endforeach
                @foreach($targets as $f)
                    <label class="list-group-item d-flex gap-2 align-items-center">
                        <input class="form-check-input mt-0" type="radio" name="to" value="folder:{{ $f->id }}" @checked($current === $f->id)>
                        <i class="ri-folder-3-fill text-warning"></i>
                        <span class="text-truncate">{{ $f->path_label }}</span> <span class="small text-muted text-nowrap">{{ $f->space_label }}</span>
                    </label>
                @endforeach
            </div>
            <div class="p-3 border-top d-flex gap-2">
                <button class="btn btn-primary">Move here</button>
                <a href="{{ $back }}" class="btn btn-light">Cancel</a>
            </div>
        </form>
    </x-cb.card>
</div></div></div>
@endsection
