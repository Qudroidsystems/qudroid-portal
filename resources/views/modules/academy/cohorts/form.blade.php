@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $cohort->exists; @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $cohort->name : 'New cohort'" icon="ri-team-line" :subtitle="$course->title"
               :back="$editing ? route('academy.cohorts.show', $cohort) : route('academy.courses.show', $course)" :back-label="$editing ? 'Cohort' : 'Course'" />

    @include('partials.flash')

    <form method="POST" action="{{ $editing ? route('academy.cohorts.update', $cohort) : route('academy.cohorts.store', $course) }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Cohort" icon="ri-team-line">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="150" value="{{ old('name', $cohort->name) }}"></div>
                        <div class="col-md-4"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase" required maxlength="40" value="{{ old('code', $cohort->code) }}"></div>
                        <div class="col-md-3"><label class="form-label">Starts</label><input name="starts_on" type="date" class="form-control" value="{{ old('starts_on', $cohort->starts_on?->format('Y-m-d')) }}"></div>
                        <div class="col-md-3"><label class="form-label">Ends</label><input name="ends_on" type="date" class="form-control" value="{{ old('ends_on', $cohort->ends_on?->format('Y-m-d')) }}"></div>
                        <div class="col-md-3"><label class="form-label">Registration opens</label><input name="registration_opens_on" type="date" class="form-control" value="{{ old('registration_opens_on', $cohort->registration_opens_on?->format('Y-m-d')) }}"></div>
                        <div class="col-md-3"><label class="form-label">Registration closes</label><input name="registration_closes_on" type="date" class="form-control" value="{{ old('registration_closes_on', $cohort->registration_closes_on?->format('Y-m-d')) }}"></div>
                        <div class="col-md-6"><label class="form-label">Instructor</label>
                            <select name="instructor_id" class="form-select"><option value="">Not assigned</option>
                                @foreach($instructors as $id => $name)<option value="{{ $id }}" @selected(old('instructor_id', $cohort->instructor_id) == $id)>{{ $name }}</option>@endforeach
                            </select>
                        </div>
                        <div class="col-md-6"><label class="form-label">Schedule</label><input name="schedule" class="form-control" maxlength="255" placeholder="e.g. Tue & Thu, 4–7pm" value="{{ old('schedule', $cohort->schedule) }}"></div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Seats and fee" icon="ri-money-dollar-circle-line">
                    <div class="mb-2"><label class="form-label">Capacity</label><input name="capacity" type="number" min="1" class="form-control" value="{{ old('capacity', $cohort->capacity) }}" placeholder="No limit"></div>
                    <div class="mb-2"><label class="form-label">Delivery</label>
                        <select name="delivery_mode" class="form-select"><option value="">Same as course ({{ $course->mode_label }})</option>
                            @foreach(\App\Models\Academy\Course::MODES as $k => $v)<option value="{{ $k }}" @selected(old('delivery_mode', $cohort->delivery_mode) === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    <div class="mb-2"><label class="form-label">Fee for this cohort</label><input name="fee_override" type="number" step="0.01" min="0" class="form-control" value="{{ old('fee_override', $cohort->fee_override) }}" placeholder="Course fee: {{ \App\Support\Money::format($course->fee, $course->currency) }}"><div class="form-text">Leave empty to use the course fee.</div></div>
                </x-cb.card>
                <x-cb.card title="Attendance check-in" icon="ri-map-pin-user-line">
                    <div class="mb-2"><label class="form-label">Campus</label>
                        <select name="place_id" class="form-select"><option value="">None / online</option>
                            @foreach($places as $pl)<option value="{{ $pl->id }}" @selected(old('place_id', $cohort->place_id) == $pl->id)>{{ $pl->name }}@if($pl->lat === null) (no map location yet)@else ({{ $pl->radius_m }} m)@endif</option>@endforeach
                        </select>
                        <div class="form-text">Students must be within the campus radius to check in. @can('Manage check-in places')<a href="{{ route('checkin.places.index') }}">Set up campuses</a>@endcan</div>
                    </div>
                    <div class="mb-2"><label class="form-label">How students check in</label>
                        <select name="checkin_mode" class="form-select">@foreach(\App\Models\Academy\Cohort::CHECKIN_MODES as $k => $v)<option value="{{ $k }}" @selected(old('checkin_mode', $cohort->checkin_mode ?? 'either') === $k)>{{ $v }}</option>@endforeach</select>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label small">Opens (min before)</label><input name="checkin_opens_minutes" type="number" min="0" max="240" class="form-control" value="{{ old('checkin_opens_minutes', $cohort->checkin_opens_minutes ?? 15) }}"></div>
                        <div class="col-6"><label class="form-label small">Late after (min)</label><input name="late_after_minutes" type="number" min="0" max="240" class="form-control" value="{{ old('late_after_minutes', $cohort->late_after_minutes ?? 15) }}"></div>
                    </div>
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="auto_absent" value="1" id="autoabsent" @checked(old('auto_absent', $cohort->auto_absent ?? true))>
                        <label class="form-check-label" for="autoabsent">Mark students who didn't check in absent when the class ends</label></div>
                    <div class="form-text">Students get a notification on their phone when check-in opens.</div>
                </x-cb.card>
                <x-cb.card title="Status" icon="ri-flag-line">
                    <select name="status" class="form-select mb-2">@foreach(\App\Models\Academy\Cohort::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $cohort->status) === $k)>{{ $v }}</option>@endforeach</select>
                    <div class="form-text mb-3">Students can be enrolled while the cohort is open or running.</div>
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save changes' : 'Create cohort' }}</button>
                </x-cb.card>
            </div>
        </div>
    </form>

</div></div></div>
@endsection
