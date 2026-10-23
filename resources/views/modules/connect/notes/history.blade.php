@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    @include('modules.connect.notes._head', ['subtitle' => 'Version history of "' . $note->title . '"'])

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card title="Versions" icon="ri-history-line" :count="$revisions->count()" :flush="true">
                <div class="list-group list-group-flush">
                    @foreach($revisions as $r)
                        <a href="{{ route('connect.notes.history', [$note, 'v' => $r->version]) }}" class="list-group-item list-group-item-action {{ $shown && $shown->id === $r->id ? 'active' : '' }}">
                            <div class="d-flex justify-content-between"><strong>Version {{ $r->version }}</strong>@if($r->version === $note->version)<span class="badge bg-success">Current</span>@endif</div>
                            <div class="small">{{ $r->user?->name ?? 'Someone' }} · {{ $r->created_at?->format('j M Y, H:i') }}</div>
                        </a>
                    @endforeach
                </div>
            </x-cb.card>
            <a href="{{ route('connect.notes.show', $note) }}" class="btn btn-sm btn-soft-secondary"><i class="ri-arrow-left-line"></i> Back to the note</a>
        </div>
        <div class="col-xl-8">
            <x-cb.card>
                @if($shown)
                    <div class="d-flex justify-content-between align-items-center mb-3 gap-2 flex-wrap">
                        <h5 class="mb-0">{{ $shown->title }} <span class="small text-muted">· version {{ $shown->version }}</span></h5>
                        @if($canEdit && $shown->version !== $note->version)
                            <form method="POST" action="{{ route('connect.notes.restore', [$note, $shown]) }}" onsubmit="return confirm('Make this version the current one? The current text stays in the history.')">@csrf<button class="btn btn-sm btn-primary"><i class="ri-arrow-go-back-line"></i> Restore this version</button></form>
                        @endif
                    </div>
                    <div class="md-body">{!! \App\Models\Connect\Note::render($shown->body) !!}</div>
                @else
                    <div class="text-muted">Pick a version on the left to see it.</div>
                @endif
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection

@push('styles')
@include('modules.connect.notes._styles')
@endpush
