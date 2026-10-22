@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $course->exists; @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $course->title : 'New course'" icon="ri-book-2-line"
               subtitle="Fees set here apply to new enrollments. A cohort can override the fee for one intake."
               :back="$editing ? route('academy.courses.show', $course) : route('academy.courses.index')" :back-label="$editing ? 'Course' : 'Courses'" />

    @include('modules.academy.partials.flash')

    <form method="POST" action="{{ $editing ? route('academy.courses.update', $course) : route('academy.courses.store') }}">
        @csrf @if($editing) @method('PUT') @endif
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Course details" icon="ri-file-text-line">
                    <div class="row g-3">
                        <div class="col-md-8"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="200" value="{{ old('title', $course->title) }}"></div>
                        <div class="col-md-4"><label class="form-label">Code</label><input name="code" class="form-control text-uppercase" required maxlength="30" placeholder="WEB101" value="{{ old('code', $course->code) }}"></div>
                        <div class="col-md-6"><label class="form-label">Programme</label>
                            <select name="programme_id" class="form-select"><option value="">None</option>
                                @foreach($programmes as $id => $name)<option value="{{ $id }}" @selected(old('programme_id', $course->programme_id) == $id)>{{ $name }}</option>@endforeach
                            </select>
                            @if($programmes->isEmpty())<div class="form-text"><a href="{{ route('academy.programmes.index') }}">Add programmes</a> to group courses.</div>@endif
                        </div>
                        <div class="col-md-3"><label class="form-label">Level</label>
                            <select name="level" class="form-select">@foreach(\App\Models\Academy\Course::LEVELS as $k => $v)<option value="{{ $k }}" @selected(old('level', $course->level) === $k)>{{ $v }}</option>@endforeach</select>
                        </div>
                        <div class="col-md-3"><label class="form-label">Delivery</label>
                            <select name="delivery_mode" class="form-select">@foreach(\App\Models\Academy\Course::MODES as $k => $v)<option value="{{ $k }}" @selected(old('delivery_mode', $course->delivery_mode) === $k)>{{ $v }}</option>@endforeach</select>
                        </div>
                        <div class="col-12"><label class="form-label">Summary</label><input name="summary" class="form-control" maxlength="500" value="{{ old('summary', $course->summary) }}" placeholder="One line shown in course lists"></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="8" placeholder="What students will learn, who it is for, what they need">{{ old('description', $course->description) }}</textarea></div>
                        <div class="col-md-4"><label class="form-label">Duration (weeks)</label><input name="duration_weeks" type="number" min="1" class="form-control" value="{{ old('duration_weeks', $course->duration_weeks) }}"></div>
                        <div class="col-md-8"><label class="form-label">Prerequisites</label>
                            <select name="prerequisites[]" class="form-select" multiple size="4">
                                @php $chosen = collect(old('prerequisites', $course->exists ? $course->prerequisites->pluck('id')->all() : []))->map(fn ($v) => (int) $v); @endphp
                                @foreach($others as $id => $title)<option value="{{ $id }}" @selected($chosen->contains($id))>{{ $title }}</option>@endforeach
                            </select>
                            <div class="form-text">Students must complete these first. Hold Ctrl / Cmd to pick several.</div>
                        </div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Fee" icon="ri-money-dollar-circle-line">
                    <div class="row g-2">
                        <div class="col-8"><label class="form-label">Course fee</label><input name="fee" type="number" step="0.01" min="0" class="form-control" required value="{{ old('fee', $course->fee ?? 0) }}"></div>
                        <div class="col-4"><label class="form-label">Currency</label><input name="currency" class="form-control text-uppercase" maxlength="3" required value="{{ old('currency', $course->currency) }}"></div>
                        <div class="col-12 form-check form-switch ms-1 mt-3"><input type="hidden" name="installments_allowed" value="0"><input class="form-check-input" type="checkbox" id="inst" name="installments_allowed" value="1" @checked(old('installments_allowed', $course->installments_allowed))><label class="form-check-label" for="inst">Instalments allowed</label></div>
                        <div class="col-12"><label class="form-label">Minimum first payment</label><input name="min_deposit" type="number" step="0.01" min="0" class="form-control" value="{{ old('min_deposit', $course->min_deposit) }}"><div class="form-text">Only used when instalments are allowed.</div></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Publishing" icon="ri-eye-line">
                    <select name="status" class="form-select mb-2">@foreach(\App\Models\Academy\Course::STATUSES as $k => $v)<option value="{{ $k }}" @selected(old('status', $course->status) === $k)>{{ $v }}</option>@endforeach</select>
                    <div class="form-text mb-3">Students only see published courses.</div>
                    <button class="btn btn-primary w-100">{{ $editing ? 'Save changes' : 'Create course' }}</button>
                </x-cb.card>
            </div>
        </div>
    </form>

</div></div></div>
@endsection
