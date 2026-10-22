@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$title" :icon="$icon" :back="route('connect.index')" back-label="Connect"
        :subtitle="$term !== null ? (mb_strlen($term) >= 2 ? 'Messages and files matching “' . $term . '” in your conversations.' : 'Type at least two letters.') : 'Messages you saved for later.'" />

    @include('partials.flash')

    @if($term !== null)
        <form action="{{ route('connect.search') }}" class="mb-3" style="max-width:520px">
            <div class="input-group">
                <input name="q" value="{{ $term }}" class="form-control" placeholder="Search messages and files" aria-label="Search messages" autofocus>
                <button class="btn btn-primary"><i class="ri-search-line"></i></button>
            </div>
        </form>
    @endif

    <x-cb.card :flush="true">
        @if($results->isEmpty())
            <div class="empty-state py-5"><i class="{{ $icon }}"></i><h6>{{ $term !== null ? 'Nothing found' : 'Nothing saved yet' }}</h6>
                @if($term === null)<p class="small text-muted mb-0">Use "Save" on any message to keep it here.</p>@endif</div>
        @else
            <div class="list-group list-group-flush">
                @foreach($results as $m)
                    @php $url = $m->thread_id ? route('connect.show', [$m->conversation, 'around' => $m->thread_id, 'thread' => $m->thread_id]) : route('connect.show', [$m->conversation, 'around' => $m->id]) . '#m' . $m->id; @endphp
                    <a href="{{ $url }}" class="list-group-item list-group-item-action">
                        <div class="d-flex justify-content-between gap-2 small text-muted">
                            <span><strong class="text-body">{{ $m->user?->name ?? 'Someone' }}</strong> in {{ $m->conversation->titleFor($me) }}@if($m->thread_id) · thread @endif</span>
                            <span class="text-nowrap">{{ $m->created_at->format('j M Y, H:i') }}</span>
                        </div>
                        <div class="text-truncate">@if($m->attachment_name)<i class="ri-attachment-2"></i> {{ $m->attachment_name }} @endif{{ \Illuminate\Support\Str::limit((string) $m->body, 200) }}</div>
                    </a>
                @endforeach
            </div>
        @endif
    </x-cb.card>
</div></div></div>
@endsection
