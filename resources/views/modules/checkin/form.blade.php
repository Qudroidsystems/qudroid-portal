@extends('layouts.master')
@section('title', $event->exists ? 'Edit event' : 'New event')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$event->exists ? 'Edit ' . $event->title : 'New check-in event'" icon="ri-calendar-event-line"
               subtitle="A conference, training, launch or any gathering you want a head count for." :back="$event->exists ? route('checkin.events.show', $event) : route('checkin.index')" />
    @include('partials.flash')
    <form method="POST" action="{{ $event->exists ? route('checkin.events.update', $event) : route('checkin.events.store') }}">
        @csrf @if($event->exists) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-7">
                <x-cb.card title="The event" icon="ri-information-line">
                    <div class="mb-2"><label class="form-label">Title</label><input name="title" class="form-control" value="{{ old('title', $event->title) }}" required maxlength="200" placeholder="Lagos Tech Summit 2026"></div>
                    <div class="mb-2"><label class="form-label">Description <span class="text-muted small">(shown on the sign-up page)</span></label><textarea name="description" class="form-control" rows="3">{{ old('description', $event->description) }}</textarea></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label">Starts</label><input type="datetime-local" name="starts_at" class="form-control" value="{{ old('starts_at', $event->starts_at?->format('Y-m-d\TH:i')) }}" required></div>
                        <div class="col-md-6"><label class="form-label">Ends</label><input type="datetime-local" name="ends_at" class="form-control" value="{{ old('ends_at', $event->ends_at?->format('Y-m-d\TH:i')) }}"></div>
                        <div class="col-12"><label class="form-label">Venue</label><input name="location" class="form-control" value="{{ old('location', $event->location) }}" placeholder="Landmark Centre, Victoria Island"></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Who can check in" icon="ri-group-line">
                    @foreach(\App\Models\Checkin\Event::AUDIENCES as $k => $label)
                        <div class="form-check"><input class="form-check-input" type="radio" name="audience" value="{{ $k }}" id="aud{{ $k }}" @checked(old('audience', $event->audience) === $k)><label class="form-check-label" for="aud{{ $k }}">{{ $label }}</label></div>
                    @endforeach
                    <hr>
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" name="public_registration" value="1" id="pubreg" @checked(old('public_registration', $event->public_registration))>
                        <label class="form-check-label" for="pubreg">Public sign-up page (no account needed). Each guest gets a ticket QR by e-mail.</label></div>
                    <div class="row g-2">
                        <div class="col-md-6"><label class="form-label small">Page address</label><div class="input-group input-group-sm"><span class="input-group-text">{{ url('/e') }}/</span><input name="slug" class="form-control" value="{{ old('slug', $event->slug) }}" placeholder="made from the title"></div></div>
                        <div class="col-md-3"><label class="form-label small">Capacity</label><input type="number" name="capacity" min="1" class="form-control form-control-sm" value="{{ old('capacity', $event->capacity) }}" placeholder="No limit"></div>
                        <div class="col-md-3"><label class="form-label small">Sign-up closes</label><input type="datetime-local" name="registration_closes_at" class="form-control form-control-sm" value="{{ old('registration_closes_at', $event->registration_closes_at?->format('Y-m-d\TH:i')) }}"></div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-5">
                <x-cb.card title="At the door" icon="ri-door-open-line">
                    <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" name="allow_screen" value="1" id="as" @checked(old('allow_screen', $event->allow_screen))><label class="form-check-label" for="as">Door QR on a screen (people scan it with their phone)</label></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox" name="allow_badge" value="1" id="ab" @checked(old('allow_badge', $event->allow_badge))><label class="form-check-label" for="ab">Scan badges and tickets with a phone</label></div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small">Opens (minutes before)</label><input type="number" name="opens_minutes" min="0" class="form-control form-control-sm" value="{{ old('opens_minutes', $event->opens_minutes) }}"></div>
                        <div class="col-6"><label class="form-label small">Late after (minutes)</label><input type="number" name="late_minutes" min="0" class="form-control form-control-sm" value="{{ old('late_minutes', $event->late_minutes) }}"></div>
                    </div>
                    @include('modules.checkin._location', ['m' => $event])
                </x-cb.card>
                <button class="btn btn-primary">{{ $event->exists ? 'Save' : 'Create event' }}</button>
            </div>
        </div>
    </form>
</div></div></div>
@endsection
