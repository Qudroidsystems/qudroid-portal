@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Calls" icon="ri-phone-line" :back="route('connect.index')" back-label="Connect"
        subtitle="Voice and video calls in your conversations. Start one from the phone or camera button in any chat." />

    <x-cb.card :flush="true">
        @if($calls->isEmpty())
            <div class="empty-state py-5"><i class="ri-phone-line"></i><h6>No calls yet</h6></div>
        @else
            <div class="table-responsive">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light"><tr><th>Conversation</th><th class="d-none d-md-table-cell">Started by</th><th>When</th><th class="d-none d-sm-table-cell">Length</th><th class="d-none d-md-table-cell">People</th><th></th></tr></thead>
                    <tbody>
                        @foreach($calls as $c)
                            @php
                                $joined = $c->people->whereNotNull('joined_at');
                                $missed = !$c->isLive() && $c->started_by !== $me->id && !$joined->contains('user_id', $me->id);
                            @endphp
                            <tr>
                                <td><i class="{{ $c->kind === 'audio' ? 'ri-phone-line' : 'ri-vidicon-line' }} me-1 {{ $missed ? 'text-danger' : 'text-muted' }}"></i>
                                    <a href="{{ route('connect.show', $c->conversation) }}">{{ $c->conversation->titleFor($me) }}</a>
                                    @if($missed)<span class="badge bg-danger-subtle text-danger ms-1">Missed</span>@endif</td>
                                <td class="d-none d-md-table-cell small">{{ $c->started_by === $me->id ? 'You' : $c->starter?->name }}</td>
                                <td class="small text-muted">{{ $c->started_at->format('j M, H:i') }}</td>
                                <td class="d-none d-sm-table-cell small">{{ $c->isLive() ? 'In progress' : $c->durationLabel() }}</td>
                                <td class="d-none d-md-table-cell small text-muted">{{ $joined->map(fn ($p) => $p->user_id === $me->id ? 'You' : $p->user?->first_name)->filter()->implode(', ') }}</td>
                                <td class="text-end">@if($c->isLive())<a href="{{ route('connect.calls.show', $c) }}" target="_blank" rel="noopener" class="btn btn-sm btn-success">Join</a>@endif</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-cb.card>
</div></div></div>
@endsection
