@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $cohort->exists; @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $cohort->name : 'New cohort'" icon="ri-team-line" :subtitle="$course->title"
               :back="$editing ? route('academy.cohorts.show', $cohort) : route('academy.courses.show', $course)" :back-label="$editing ? 'Cohort' : 'Course'" />

    @include('modules.academy.partials.flash')

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
