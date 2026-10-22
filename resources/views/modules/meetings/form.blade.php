@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php
        $editing = $meeting->exists;
        $dt = fn ($f) => old($f, $meeting->{$f}?->format('Y-m-d\TH:i'));
        $chosen = array_map('intval', old('attendees', $invited));
        $guestText = old('guests', collect($meeting->guests ?? [])->map(fn ($g) => trim(($g['name'] ?? '') . ($g['email'] ? ' <' . $g['email'] . '>' : '')))->implode("\n"));
    @endphp
    <x-cb.hero :title="$editing ? 'Edit meeting' : 'Schedule a meeting'" icon="ri-video-chat-line" :back="$editing ? route('meetings.show', $meeting) : route('meetings.index')" :back-label="$editing ? $meeting->title : 'Meetings'" />

    @include('partials.flash')

    <form method="POST" action="{{ $editing ? route('meetings.update', $meeting) : route('meetings.store') }}" id="meeting-form">
        @csrf
        @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card>
                    <div class="row g-3">
                        <div class="col-12"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $meeting->title) }}" maxlength="150" required placeholder="e.g. Weekly team check-in"></div>
                        <div class="col-md-6"><label class="form-label">Starts</label><input type="datetime-local" name="starts_at" id="m-start" class="form-control" value="{{ $dt('starts_at') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Ends</label><input type="datetime-local" name="ends_at" id="m-end" class="form-control" value="{{ $dt('ends_at') }}" required></div>
                        <div class="col-md-6"><label class="form-label">Room</label>
                            <select name="room_id" class="form-select" id="m-room"><option value="">No room</option>@foreach($rooms as $r)<option value="{{ $r->id }}" @selected((int) old('room_id', $meeting->room_id) === $r->id)>{{ $r->name }}{{ $r->capacity ? ' (' . $r->capacity . ')' : '' }}</option>@endforeach</select></div>
                        <div class="col-md-6" id="m-loc-wrap"><label class="form-label">Or somewhere else</label><input name="location" class="form-control" value="{{ old('location', $meeting->location) }}" maxlength="255" placeholder="e.g. Client's office, Ikeja"></div>
                        <div class="col-12"><label class="form-label">Video link</label><input type="url" name="online_url" class="form-control" value="{{ old('online_url', $meeting->online_url) }}" maxlength="500" placeholder="https://meet.google.com/… or Zoom link"></div>
                        <div class="col-12"><label class="form-label">Agenda</label><textarea name="agenda" class="form-control" rows="4" maxlength="10000">{{ old('agenda', $meeting->agenda) }}</textarea></div>
                        @if($projects->isNotEmpty())
                            <div class="col-md-6"><label class="form-label">Project</label>
                                <select name="project_id" class="form-select"><option value="">None</option>@foreach($projects as $p)<option value="{{ $p->id }}" @selected((int) old('project_id', $meeting->project_id) === $p->id)>{{ $p->code }} · {{ $p->name }}</option>@endforeach</select></div>
                        @endif
                        @if($companies->isNotEmpty())
                            <div class="col-md-6"><label class="form-label">Client</label>
                                <select name="company_id" class="form-select"><option value="">None</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected((int) old('company_id', $meeting->company_id) === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
                        @endif
                        <div class="col-md-6"><label class="form-label">Remind people</label>
                            <select name="remind_minutes" class="form-select">
                                @foreach([0 => 'No reminder', 5 => '5 minutes before', 10 => '10 minutes before', 15 => '15 minutes before', 30 => '30 minutes before', 60 => '1 hour before', 1440 => 'A day before'] as $k => $v)
                                    <option value="{{ $k }}" @selected((int) old('remind_minutes', $meeting->remind_minutes ?? 15) === $k)>{{ $v }}</option>
                                @endforeach
                            </select></div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Invite" icon="ri-team-line">
                    <input type="search" class="form-control form-control-sm mb-2" id="m-find" placeholder="Find someone">
                    <div id="m-people" style="max-height: 300px; overflow-y: auto">
                        @foreach($people as $p)
                            <div class="form-check m-person" data-name="{{ strtolower($p->name) }}">
                                <input class="form-check-input" type="checkbox" name="attendees[]" value="{{ $p->id }}" id="p{{ $p->id }}" @checked(in_array($p->id, $chosen, true))>
                                <label class="form-check-label" for="p{{ $p->id }}">{{ $p->name }}@if($p->job_title) <span class="text-muted small">· {{ $p->job_title }}</span>@endif</label>
                            </div>
                        @endforeach
                    </div>
                    <div id="m-busy" class="small mt-2"></div>
                    <label class="form-label mt-3">Outside guests</label>
                    <textarea name="guests" class="form-control" rows="3" maxlength="3000" placeholder="One per line, e.g. Ngozi Ade <ngozi@acme.ng>">{{ $guestText }}</textarea>
                    <div class="form-text">Listed on the meeting. Send them the calendar file from the meeting page.</div>
                </x-cb.card>
                <button class="btn btn-primary w-100">{{ $editing ? 'Save changes' : 'Schedule and invite' }}</button>
            </div>
        </div>
    </form>

</div></div></div>
@endsection

@push('scripts')
<script>
(function () {
    var form = document.getElementById('meeting-form'), start = document.getElementById('m-start'), end = document.getElementById('m-end');
    var room = document.getElementById('m-room'), locWrap = document.getElementById('m-loc-wrap'), busyBox = document.getElementById('m-busy');
    var meetingId = {{ $meeting->id ?? 'null' }};

    function syncRoom() { locWrap.style.display = room.value ? 'none' : ''; }
    room.addEventListener('change', syncRoom); syncRoom();

    var lastLen = end.value && start.value ? (new Date(end.value) - new Date(start.value)) : 3600000;
    start.addEventListener('change', function () {
        if (!start.value) return;
        var e = new Date(new Date(start.value).getTime() + (lastLen > 0 ? lastLen : 3600000));
        var pad = function (n) { return String(n).padStart(2, '0'); };
        end.value = e.getFullYear() + '-' + pad(e.getMonth() + 1) + '-' + pad(e.getDate()) + 'T' + pad(e.getHours()) + ':' + pad(e.getMinutes());
        check();
    });
    end.addEventListener('change', function () { lastLen = new Date(end.value) - new Date(start.value); check(); });

    document.getElementById('m-find').addEventListener('input', function () {
        var q = this.value.trim().toLowerCase();
        document.querySelectorAll('.m-person').forEach(function (el) { el.style.display = !q || el.dataset.name.indexOf(q) !== -1 ? '' : 'none'; });
    });

    var timer;
    document.getElementById('m-people').addEventListener('change', function () { clearTimeout(timer); timer = setTimeout(check, 300); });

    function check() {
        var ids = Array.prototype.map.call(form.querySelectorAll('input[name="attendees[]"]:checked'), function (i) { return i.value; });
        if (!ids.length || !start.value || !end.value) { busyBox.innerHTML = ''; return; }
        var body = new FormData();
        body.append('_token', form.querySelector('input[name="_token"]').value);
        body.append('starts_at', start.value); body.append('ends_at', end.value);
        if (meetingId) body.append('meeting_id', meetingId);
        ids.forEach(function (id) { body.append('attendees[]', id); });
        fetch(@json(route('meetings.check')), { method: 'POST', body: body, headers: { 'Accept': 'application/json' } })
            .then(function (r) { return r.ok ? r.json() : { busy: [] }; })
            .then(function (d) {
                busyBox.innerHTML = '';
                (d.busy || []).forEach(function (b) {
                    var div = document.createElement('div');
                    div.className = 'text-warning';
                    div.textContent = '⚠ ' + b.name + ' is ' + b.why;
                    busyBox.appendChild(div);
                });
            }).catch(function () {});
    }
    check();
})();
</script>
@endpush
