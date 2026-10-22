@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $counts = $meeting->attendees->countBy('response'); @endphp
    <x-cb.hero :title="$meeting->title" icon="ri-video-chat-line" :subtitle="$meeting->whenLabel() . ($meeting->whereLabel() ? ' · ' . $meeting->whereLabel() : '')" :back="route('meetings.index')" back-label="Meetings">
        <x-slot:actions>
            <a href="{{ route('meetings.ics', $meeting) }}" class="cb-hero-btn"><i class="ri-calendar-check-line"></i>Add to my calendar</a>
            @if($canManage && !$meeting->isCancelled())<a href="{{ route('meetings.edit', $meeting) }}" class="cb-hero-btn"><i class="ri-pencil-line"></i>Edit</a>@endif
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @if($meeting->isCancelled())
        <div class="cb-banner danger"><i class="ri-close-circle-line"></i><div>This meeting was cancelled.@if($meeting->cancel_reason) {{ $meeting->cancel_reason }}@endif</div></div>
    @endif

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card>
                <dl class="row mb-0">
                    <dt class="col-sm-3 text-muted fw-normal">When</dt><dd class="col-sm-9">{{ $meeting->starts_at->format('l j F Y') }}, {{ $meeting->starts_at->format('H:i') }}–{{ $meeting->ends_at->format('H:i') }} <span class="text-muted small">({{ $meeting->starts_at->diffForHumans() }})</span></dd>
                    @if($meeting->room)<dt class="col-sm-3 text-muted fw-normal">Room</dt><dd class="col-sm-9">{{ $meeting->room->name }}</dd>@endif
                    @if($meeting->location)<dt class="col-sm-3 text-muted fw-normal">Where</dt><dd class="col-sm-9">{{ $meeting->location }}</dd>@endif
                    @if($meeting->online_url)<dt class="col-sm-3 text-muted fw-normal">Video</dt><dd class="col-sm-9"><a href="{{ $meeting->online_url }}" target="_blank" rel="noopener" class="btn btn-sm btn-primary"><i class="ri-vidicon-line"></i> Join</a> <span class="small text-muted text-break">{{ $meeting->online_url }}</span></dd>@endif
                    <dt class="col-sm-3 text-muted fw-normal">Organiser</dt><dd class="col-sm-9">{{ $meeting->organizer->name }}</dd>
                    @if($meeting->project)<dt class="col-sm-3 text-muted fw-normal">Project</dt><dd class="col-sm-9"><a href="{{ route('projects.show', $meeting->project) }}">{{ $meeting->project->code }} · {{ $meeting->project->name }}</a></dd>@endif
                    @if($meeting->company)<dt class="col-sm-3 text-muted fw-normal">Client</dt><dd class="col-sm-9">@can('View clients')<a href="{{ route('crm.companies.show', $meeting->company) }}">{{ $meeting->company->name }}</a>@else{{ $meeting->company->name }}@endcan</dd>@endif
                </dl>
                @if($meeting->agenda)
                    <h6 class="mt-4">Agenda</h6>
                    <div style="white-space: pre-line">{{ $meeting->agenda }}</div>
                @endif
            </x-cb.card>

            @if($meeting->starts_at->isPast() && !$meeting->isCancelled())
                <x-cb.card title="Minutes" icon="ri-file-text-line">
                    @if($canManage)
                        <form method="POST" action="{{ route('meetings.minutes', $meeting) }}">
                            @csrf @method('PUT')
                            <textarea name="minutes" class="form-control mb-2" rows="8" maxlength="20000" placeholder="What was decided, and who does what by when">{{ old('minutes', $meeting->minutes) }}</textarea>
                            <button class="btn btn-primary">Save minutes</button>
                            <span class="form-text ms-2">Everyone who came is told when minutes are first posted.</span>
                        </form>
                    @elseif($meeting->minutes)
                        <div style="white-space: pre-line">{{ $meeting->minutes }}</div>
                    @else
                        <div class="text-muted small">No minutes yet.</div>
                    @endif
                </x-cb.card>
            @endif
        </div>

        <div class="col-xl-4">
            @if($mine && !$meeting->isCancelled() && !$meeting->isOver() && $meeting->organizer_id !== auth()->id())
                <x-cb.card title="Are you going?" icon="ri-question-answer-line">
                    <form method="POST" action="{{ route('meetings.respond', $meeting) }}">
                        @csrf
                        <div class="btn-group w-100 mb-2" role="group">
                            @foreach(['accepted' => 'Going', 'tentative' => 'Maybe', 'declined' => 'Can\'t go'] as $k => $v)
                                <input type="radio" class="btn-check" name="response" value="{{ $k }}" id="r-{{ $k }}" @checked($mine->response === $k)>
                                <label class="btn btn-outline-primary btn-sm" for="r-{{ $k }}">{{ $v }}</label>
                            @endforeach
                        </div>
                        <input name="note" class="form-control mb-2" maxlength="255" value="{{ $mine->note }}" placeholder="Note to the organiser (optional)">
                        <button class="btn btn-primary w-100">Send answer</button>
                    </form>
                </x-cb.card>
            @endif

            <x-cb.card title="People" icon="ri-team-line" :count="$meeting->attendees->count()" :flush="true">
                <div class="px-3 pt-2 small text-muted">{{ $counts['accepted'] ?? 0 }} going · {{ $counts['tentative'] ?? 0 }} maybe · {{ $counts['declined'] ?? 0 }} not going · {{ $counts['pending'] ?? 0 }} not answered</div>
                <ul class="list-group list-group-flush">
                    @foreach($meeting->attendees->sortBy(fn ($a) => [$a->user_id === $meeting->organizer_id ? 0 : 1, $a->user?->name]) as $a)
                        <li class="list-group-item d-flex justify-content-between align-items-start gap-2 small">
                            <div class="d-flex gap-2 align-items-center">
                                <img src="{{ $a->user->avatar_url }}" class="rounded-circle" width="28" height="28" alt="">
                                <div>{{ $a->user->name }}@if($a->user_id === $meeting->organizer_id) <span class="text-muted">· organiser</span>@endif
                                    @if(isset($busy[$a->user_id]))<div class="text-warning">⚠ {{ $busy[$a->user_id] }}</div>@endif
                                    @if($a->note)<div class="text-muted">“{{ $a->note }}”</div>@endif
                                </div>
                            </div>
                            @include('modules.meetings._pill', ['value' => $a->response])
                        </li>
                    @endforeach
                    @foreach($meeting->guests ?? [] as $g)
                        <li class="list-group-item small"><i class="ri-user-shared-line text-muted"></i> {{ $g['name'] }}@if(!empty($g['email'])) <span class="text-muted">· {{ $g['email'] }}</span>@endif <span class="badge bg-secondary-subtle text-secondary">guest</span></li>
                    @endforeach
                </ul>
            </x-cb.card>

            @if($canManage && !$meeting->isCancelled() && !$meeting->isOver())
                <form method="POST" action="{{ route('meetings.cancel', $meeting) }}" onsubmit="return confirm('Cancel this meeting? Everyone invited is told.')">
                    @csrf
                    <input name="reason" class="form-control mb-2" maxlength="255" placeholder="Reason (optional)">
                    <button class="btn btn-soft-danger w-100">Cancel meeting</button>
                </form>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
