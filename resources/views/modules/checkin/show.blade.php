@extends('layouts.master')
@section('title', $event->title)

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$event->title" :icon="$event->icon()" :subtitle="$event->kindLabel() . ' · ' . $event->whenLabel() . ($event->location ? ' · ' . $event->location : '')" :back="route('checkin.index')" back-label="Check-in">
        <x-slot:pills>
            @if($event->status === 'open')
                <span class="cb-meta-pill"><i class="ri-door-open-line"></i>{{ $event->isOpenNow() ? 'Open now' : 'Opens ' . $event->opensAt()->format('D j M, g:i A') }}</span>
            @else
                <span class="cb-meta-pill"><i class="ri-door-closed-line"></i>{{ ucfirst($event->status) }}</span>
            @endif
            <span class="cb-meta-pill"><i class="ri-time-line"></i>Late after {{ $event->lateAt()->format('g:i A') }}</span>
            @if($event->require_location)<span class="cb-meta-pill"><i class="ri-map-pin-line"></i>Within {{ $event->radius_m }} m</span>@endif
        </x-slot:pills>
        <x-slot:actions>
            @if($event->allow_screen)<a href="{{ route('checkin.events.screen', $event) }}" target="_blank" class="cb-hero-btn"><i class="ri-tv-2-line"></i>Door screen</a>@endif
            @if($event->allow_badge)<a href="{{ route('checkin.events.scanner', $event) }}" class="cb-hero-btn"><i class="ri-qr-scan-2-line"></i>Scan badges</a>@endif
            <a href="{{ route('checkin.events.export', $event) }}" class="cb-hero-btn"><i class="ri-download-2-line"></i>CSV</a>
            @if($event->kind === 'event')<a href="{{ route('checkin.events.edit', $event) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a>@endif
        </x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3 mb-1">
        @php $cards = [['Checked in', $stats['in'] . ($stats['expected'] ? ' / ' . $stats['expected'] : ''), 'ri-user-follow-line', 'success'], ['Late', $stats['late'], 'ri-alarm-warning-line', 'warning']];
            if ($event->kind === 'staff') $cards[] = ['Clocked out', $stats['out'], 'ri-logout-box-r-line', 'info'];
            if ($event->kind === 'event') $cards[] = ['Registered', $stats['registered'] . ($stats['capacity'] ? ' / ' . $stats['capacity'] : ''), 'ri-ticket-2-line', 'primary'];
            if ($stats['expected']) $cards[] = ['Not here yet', max(0, $stats['expected'] - $stats['in']), 'ri-user-unfollow-line', 'danger']; @endphp
        @foreach($cards as [$label, $val, $ico, $tone])
            <div class="col-6 col-md-3"><div class="cb-card p-3 d-flex align-items-center gap-3">
                <i class="{{ $ico }} fs-2 text-{{ $tone }}"></i><div><div class="fs-4 fw-semibold">{{ $val }}</div><div class="small text-muted">{{ $label }}</div></div>
            </div></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Checked in" icon="ri-user-follow-line" :count="$records->count()" :flush="true">
                <x-slot:tools>
                    @if($event->status === 'open')
                        <form method="POST" action="{{ route('checkin.events.close', $event) }}" onsubmit="return confirm('Close check-in?{{ $event->kind === 'class' ? ' Students who did not check in will be marked absent.' : '' }}')">@csrf<button class="btn btn-sm btn-soft-danger">Close check-in</button></form>
                    @else
                        <form method="POST" action="{{ route('checkin.events.reopen', $event) }}">@csrf<button class="btn btn-sm btn-soft-primary">Reopen</button></form>
                    @endif
                </x-slot:tools>
                @if($records->isEmpty())
                    <div class="p-4 text-center text-muted">Nobody yet. Put the door screen up or start scanning.</div>
                @else
                <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                    <thead><tr><th>Name</th><th>In</th>@if($event->kind === 'staff')<th>Out</th>@endif<th>How</th><th>Status</th><th></th></tr></thead>
                    <tbody>
                    @foreach($records as $r)
                        <tr>
                            <td><div class="d-flex align-items-center gap-2">
                                @if($r->user)<img src="{{ $r->user->avatar_url }}" class="rounded-circle" width="28" height="28" alt="" style="object-fit:cover">@else<span class="avatar-xs rounded-circle bg-light d-inline-flex align-items-center justify-content-center"><i class="ri-user-line"></i></span>@endif
                                <div><div class="fw-medium">{{ $r->personName() }}</div>@if($r->guest?->organisation)<small class="text-muted">{{ $r->guest->organisation }}</small>@endif</div>
                            </div></td>
                            <td class="small">{{ $r->checked_in_at->format('g:i A') }}</td>
                            @if($event->kind === 'staff')<td class="small">{{ $r->checked_out_at?->format('g:i A') ?? '—' }}</td>@endif
                            <td class="small text-muted">{{ \App\Models\Checkin\Record::METHODS[$r->method] ?? $r->method }}@if($r->scanner) ({{ $r->scanner->name }})@endif @if($r->distance_m !== null)· {{ $r->distance_m }} m @endif</td>
                            <td>
                                <form method="POST" action="{{ route('checkin.events.mark', $event) }}" class="d-inline">@csrf
                                    <input type="hidden" name="{{ $r->guest_id ? 'guest_id' : 'user_id' }}" value="{{ $r->guest_id ?? $r->user_id }}">
                                    <input type="hidden" name="status" value="{{ $r->status === 'late' ? 'present' : 'late' }}">
                                    <button class="badge border-0 {{ $r->status === 'late' ? 'bg-warning-subtle text-warning' : 'bg-success-subtle text-success' }}" title="Switch to {{ $r->status === 'late' ? 'present' : 'late' }}">{{ ucfirst($r->status) }}</button>
                                </form>
                            </td>
                            <td class="text-end"><form method="POST" action="{{ route('checkin.records.undo', $r) }}" onsubmit="return confirm('Remove this check-in?')">@csrf @method('DELETE')<button class="btn btn-sm btn-link text-muted p-0" aria-label="Remove check-in"><i class="ri-close-line"></i></button></form></td>
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @endif
            </x-cb.card>

            @if($guests->isNotEmpty())
                <x-cb.card title="Registered guests" icon="ri-ticket-2-line" :count="$guests->whereNull('cancelled_at')->count()" :flush="true">
                    <div class="table-responsive"><table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Name</th><th>Contact</th><th>Ticket</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach($guests as $g)
                            <tr class="{{ $g->cancelled_at ? 'text-muted text-decoration-line-through' : '' }}">
                                <td><div class="fw-medium">{{ $g->name }}</div><small class="text-muted">{{ $g->organisation }}</small></td>
                                <td class="small">{{ $g->email }}<br>{{ $g->phone }}</td>
                                <td class="small font-monospace">{{ $g->code }}</td>
                                <td class="small">@if($g->record)<span class="badge bg-success-subtle text-success">In {{ $g->record->checked_in_at->format('g:i A') }}</span>@elseif($g->cancelled_at) Cancelled @else Registered @endif</td>
                                <td class="text-end text-nowrap">
                                    @if(!$g->record && !$g->cancelled_at)
                                        <form method="POST" action="{{ route('checkin.events.mark', $event) }}" class="d-inline">@csrf<input type="hidden" name="guest_id" value="{{ $g->id }}"><input type="hidden" name="status" value="present"><button class="btn btn-sm btn-soft-success">Mark in</button></form>
                                        @if($g->email)<form method="POST" action="{{ route('checkin.events.guests.resend', [$event, $g]) }}" class="d-inline">@csrf<button class="btn btn-sm btn-light" title="E-mail the ticket again"><i class="ri-mail-send-line"></i></button></form>@endif
                                        <form method="POST" action="{{ route('checkin.events.guests.cancel', [$event, $g]) }}" class="d-inline" onsubmit="return confirm('Cancel {{ $g->name }}\'s ticket?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light" title="Cancel ticket"><i class="ri-close-line"></i></button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                </x-cb.card>
            @endif
        </div>

        <div class="col-xl-4">
            @if($missing->isNotEmpty())
                <x-cb.card title="Not here yet" icon="ri-user-unfollow-line" :count="$missing->count()" :flush="true">
                    <div style="max-height:420px;overflow-y:auto">
                        @foreach($missing as $u)
                            <div class="d-flex align-items-center gap-2 px-3 py-2 border-bottom">
                                <img src="{{ $u->avatar_url }}" class="rounded-circle" width="26" height="26" alt="" style="object-fit:cover">
                                <span class="flex-grow-1 small text-truncate">{{ $u->name }}</span>
                                <form method="POST" action="{{ route('checkin.events.mark', $event) }}" class="d-flex gap-1">@csrf<input type="hidden" name="user_id" value="{{ $u->id }}">
                                    <button name="status" value="present" class="btn btn-sm btn-soft-success py-0">In</button>
                                    <button name="status" value="late" class="btn btn-sm btn-soft-warning py-0">Late</button>
                                </form>
                            </div>
                        @endforeach
                    </div>
                </x-cb.card>
            @endif

            @if($event->kind === 'event')
                @if($event->public_registration && $event->slug)
                    <x-cb.card title="Sign-up page" icon="ri-links-line">
                        <input class="form-control form-control-sm mb-2" readonly value="{{ route('checkin.public.register', $event->slug) }}" onclick="this.select()" aria-label="Sign-up link">
                        <a href="{{ route('checkin.public.register', $event->slug) }}" target="_blank" class="btn btn-sm btn-light">Open page</a>
                    </x-cb.card>
                @endif
                <x-cb.card title="Register a guest" icon="ri-user-add-line">
                    <form method="POST" action="{{ route('checkin.events.guests', $event) }}">@csrf
                        <input name="name" class="form-control form-control-sm mb-1" placeholder="Full name" required>
                        <input name="email" type="email" class="form-control form-control-sm mb-1" placeholder="E-mail (the ticket is sent here)">
                        <div class="d-flex gap-1 mb-2"><input name="phone" class="form-control form-control-sm" placeholder="Phone"><input name="organisation" class="form-control form-control-sm" placeholder="Organisation"></div>
                        <button class="btn btn-sm btn-primary">Register</button>
                    </form>
                </x-cb.card>
                <x-cb.card title="People and hosts" icon="ri-team-line">
                    <form method="POST" action="{{ route('checkin.events.people', $event) }}">@csrf
                        <select name="users[]" multiple class="form-select form-select-sm mb-1" size="5" aria-label="People">
                            @foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach
                        </select>
                        <div class="d-flex gap-1"><select name="role" class="form-select form-select-sm"><option value="guest">As attendees</option><option value="host">As hosts (can scan)</option></select><button class="btn btn-sm btn-primary">Add</button></div>
                    </form>
                    @foreach($people as $p)
                        <div class="d-flex justify-content-between small mt-2"><span>{{ $p->name }} @if($p->pivot->role === 'host')<span class="badge bg-info-subtle text-info">Host</span>@endif</span>
                            <form method="POST" action="{{ route('checkin.events.people.remove', [$event, $p]) }}">@csrf @method('DELETE')<button class="btn btn-link btn-sm p-0 text-muted" aria-label="Remove"><i class="ri-close-line"></i></button></form></div>
                    @endforeach
                </x-cb.card>
            @else
                <x-cb.card title="Door rules" icon="ri-settings-3-line">
                    <form method="POST" action="{{ route('checkin.events.update', $event) }}">@csrf @method('PUT')
                        <div class="row g-2 mb-2">
                            <div class="col-6"><label class="form-label small">Opens (min before)</label><input type="number" name="opens_minutes" class="form-control form-control-sm" value="{{ $event->opens_minutes }}"></div>
                            <div class="col-6"><label class="form-label small">Late after (min)</label><input type="number" name="late_minutes" class="form-control form-control-sm" value="{{ $event->late_minutes }}"></div>
                        </div>
                        @include('modules.checkin._location', ['m' => $event])
                        <button class="btn btn-sm btn-primary mt-2">Save</button>
                    </form>
                </x-cb.card>
            @endif
            @if($event->kind === 'event')
                <form method="POST" action="{{ route('checkin.events.destroy', $event) }}" onsubmit="return confirm('Cancel this event?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Cancel event</button></form>
            @endif
        </div>
    </div>
</div></div></div>
@endsection
