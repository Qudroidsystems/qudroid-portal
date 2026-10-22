@extends('layouts.master')

@push('styles')
<style>
    .mcal { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); border-top: 1px solid var(--cb-border, #e2e8f0); border-left: 1px solid var(--cb-border, #e2e8f0); }
    .mcal-h { padding: 6px 8px; font-size: 11px; text-transform: uppercase; color: var(--cb-muted, #64748b); border-right: 1px solid var(--cb-border, #e2e8f0); border-bottom: 1px solid var(--cb-border, #e2e8f0); }
    .mcal-d { min-height: 112px; padding: 4px 6px 6px; border-right: 1px solid var(--cb-border, #e2e8f0); border-bottom: 1px solid var(--cb-border, #e2e8f0); position: relative; }
    .mcal-d.other { background: rgba(148, 163, 184, .06); }
    .mcal-d.other .mcal-n { opacity: .45; }
    .mcal-d.today .mcal-n { background: var(--cb-teal, #0d9488); color: #fff; border-radius: 50%; }
    .mcal-n { display: inline-flex; width: 24px; height: 24px; align-items: center; justify-content: center; font-size: 12px; font-weight: 600; }
    .mcal-add { position: absolute; top: 4px; right: 6px; opacity: 0; font-size: 14px; }
    .mcal-d:hover .mcal-add { opacity: .7; }
    .mcal-e { display: block; font-size: 11px; line-height: 1.3; padding: 2px 5px; border-radius: 4px; margin-top: 2px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; text-decoration: none; }
    .mcal-wd { display: none; }
    @media (max-width: 767.98px) {
        .mcal { grid-template-columns: 1fr; }
        .mcal-h { display: none; }
        .mcal-d { min-height: 0; }
        .mcal-d.other, .mcal-d.empty { display: none; }
        .mcal-wd { display: inline; font-size: 12px; color: var(--cb-muted, #64748b); margin-left: 4px; }
        .mcal-e { white-space: normal; font-size: 12px; }
    }
</style>
@endpush

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Calendar" icon="ri-calendar-2-line" subtitle="Your meetings, task deadlines, leave and public holidays.">
        <x-slot:actions>
            <a href="{{ route('meetings.index') }}" class="cb-hero-btn"><i class="ri-list-check"></i>Meetings</a>
            <a href="{{ route('meetings.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>Schedule</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @php $q = request('everyone') ? ['everyone' => 1] : []; @endphp
    <x-cb.card :flush="true">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3">
            <div class="d-flex align-items-center gap-2">
                <a href="{{ route('meetings.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')] + $q) }}" class="btn btn-sm btn-soft-secondary" aria-label="Previous month"><i class="ri-arrow-left-s-line"></i></a>
                <h5 class="mb-0" style="min-width: 150px; text-align: center">{{ $month->format('F Y') }}</h5>
                <a href="{{ route('meetings.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')] + $q) }}" class="btn btn-sm btn-soft-secondary" aria-label="Next month"><i class="ri-arrow-right-s-line"></i></a>
                <a href="{{ route('meetings.calendar', $q) }}" class="btn btn-sm btn-link">Today</a>
            </div>
            <div class="d-flex flex-wrap gap-2 small align-items-center">
                <span class="badge bg-primary-subtle text-primary">Meeting</span>
                <span class="badge bg-warning-subtle text-warning">Not answered</span>
                <span class="badge bg-info-subtle text-info">Task due</span>
                <span class="badge bg-success-subtle text-success">Leave</span>
                <span class="badge bg-danger-subtle text-danger">Holiday</span>
                @can('Manage meetings')
                    <a href="{{ route('meetings.calendar', ['month' => $month->format('Y-m')] + (request('everyone') ? [] : ['everyone' => 1])) }}" class="btn btn-sm btn-soft-secondary ms-1">{{ request('everyone') ? 'Just mine' : 'All meetings' }}</a>
                @endcan
            </div>
        </div>
        <div class="mcal">
            @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $wd)<div class="mcal-h">{{ $wd }}</div>@endforeach
            @foreach($days as $date => $items)
                @php $d = \Illuminate\Support\Carbon::parse($date); @endphp
                <div class="mcal-d {{ $d->month !== $month->month ? 'other' : '' }} {{ $d->isToday() ? 'today' : '' }} {{ $items->isEmpty() ? 'empty' : '' }}">
                    <span class="mcal-n">{{ $d->day }}</span><span class="mcal-wd">{{ $d->format('l') }}</span>
                    @if(!$d->isPast() || $d->isToday())<a href="{{ route('meetings.create', ['date' => $date]) }}" class="mcal-add" title="Schedule on {{ $d->format('j M') }}"><i class="ri-add-line"></i></a>@endif
                    @foreach($items as $i)
                        @php $label = ($i['all_day'] ? '' : $i['start']->format('H:i') . ' ') . $i['title']; @endphp
                        @if($i['url'])
                            <a href="{{ $i['url'] }}" class="mcal-e bg-{{ $i['tone'] }}-subtle text-{{ $i['tone'] }}" title="{{ $label }}{{ $i['meta'] ? ' · ' . $i['meta'] : '' }}">{{ $label }}</a>
                        @else
                            <span class="mcal-e bg-{{ $i['tone'] }}-subtle text-{{ $i['tone'] }}" title="{{ $label }}">{{ $label }}</span>
                        @endif
                    @endforeach
                </div>
            @endforeach
        </div>
    </x-cb.card>

    <x-cb.card title="See your meetings in Google, Outlook or Apple Calendar" icon="ri-calendar-todo-line">
        <p class="small text-muted mb-2">Subscribe to this private link once and your portal meetings show up in your usual calendar, updating by themselves (calendars check every few hours). Keep it to yourself: anyone with it can see your meeting list.</p>
        <div class="input-group input-group-sm mb-2" style="max-width:720px">
            <input class="form-control" id="feedUrl" value="{{ $feedUrl }}" readonly aria-label="Calendar link">
            <button class="btn btn-soft-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('feedUrl').value).then(() => { this.textContent = 'Copied'; })">Copy</button>
        </div>
        <div class="small text-muted">
            <strong>Google Calendar:</strong> Other calendars → + → From URL → paste. ·
            <strong>Outlook:</strong> Add calendar → Subscribe from web. ·
            <strong>Apple Calendar:</strong> File → New Calendar Subscription.
        </div>
        <form method="POST" action="{{ route('meetings.feed.reset') }}" class="mt-2" onsubmit="return confirm('Make a new link? Calendars using the old one stop updating.')">@csrf<button class="btn btn-sm btn-link px-0">Make a new link</button></form>
    </x-cb.card>

</div></div></div>
@endsection
