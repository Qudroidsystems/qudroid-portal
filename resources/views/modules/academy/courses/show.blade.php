@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="$course->title" icon="ri-book-2-line" :subtitle="$course->summary" :back="route('academy.courses.index')" back-label="Courses">
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $course->code }}</span>
            @if($course->programme)<span class="cb-meta-pill"><i class="ri-stack-line me-1"></i>{{ $course->programme->name }}</span>@endif
            <span class="cb-meta-pill">{{ $course->level_label }}</span>
            <span class="cb-meta-pill">{{ $course->mode_label }}</span>
            @if($course->duration_weeks)<span class="cb-meta-pill"><i class="ri-time-line me-1"></i>{{ $course->duration_weeks }} weeks</span>@endif
            @if($manager)<span class="cb-meta-pill">{{ \App\Models\Academy\Course::STATUSES[$course->status] ?? $course->status }}</span>@endif
        </x-slot:pills>
        @if($manager)
            <x-slot:actions>
                <a href="{{ route('academy.cohorts.create', $course) }}" class="cb-hero-btn"><i class="ri-add-line"></i>New cohort</a>
                <a href="{{ route('academy.courses.edit', $course) }}" class="cb-hero-btn"><i class="ri-edit-line"></i>Edit</a>
            </x-slot:actions>
        @endif
    </x-cb.hero>

    @include('modules.academy.partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="About this course" icon="ri-file-text-line">
                @if($course->description)<div style="white-space: pre-line">{{ $course->description }}</div>@else<p class="text-muted mb-0">No description yet.</p>@endif
                @if($course->prerequisites->isNotEmpty())
                    <hr><strong>Complete first:</strong>
                    @foreach($course->prerequisites as $p)<a href="{{ route('academy.courses.show', $p) }}" class="badge bg-light text-body ms-1">{{ $p->title }}</a>@endforeach
                @endif
            </x-cb.card>

            <x-cb.card title="Cohorts" icon="ri-team-line" :count="$cohorts->count()" :flush="true">
                @if($cohorts->isEmpty())
                    <div class="empty-state"><i class="ri-calendar-event-line"></i><h6>No cohorts yet</h6>@if($manager)<p><a href="{{ route('academy.cohorts.create', $course) }}">Create a cohort</a> to start taking registrations.</p>@endif</div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Cohort</th><th>Dates</th><th>Registration</th><th>Status</th><th class="num">Fee</th><th class="num">Seats</th></tr></thead>
                        <tbody>
                        @foreach($cohorts as $c)
                            <tr>
                                <td data-label="Cohort">@if($manager)<a href="{{ route('academy.cohorts.show', $c) }}" class="fw-semibold">{{ $c->name }}</a>@else<span class="fw-semibold">{{ $c->name }}</span>@endif<div class="small text-muted">{{ $c->code }}@if($c->instructor) · {{ $c->instructor->name }}@endif @if($c->schedule) · {{ $c->schedule }}@endif</div></td>
                                <td data-label="Dates">{{ $c->starts_on?->format('j M Y') ?? '—' }} – {{ $c->ends_on?->format('j M Y') ?? '—' }}</td>
                                <td data-label="Registration">@if($c->registration_closes_on)closes {{ $c->registration_closes_on->format('j M Y') }}@else — @endif</td>
                                <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $c->status, 'label' => $c->status_label])</td>
                                <td data-label="Fee" class="num">{{ \App\Support\Money::format($c->fee_override ?? $course->fee, $course->currency) }}</td>
                                <td data-label="Seats" class="num">{{ $c->seats }}{{ $c->capacity ? ' / ' . $c->capacity : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Fee" icon="ri-money-dollar-circle-line">
                <div class="fs-3 fw-bold">{{ \App\Support\Money::format($course->fee, $course->currency) }}</div>
                @if($course->installments_allowed)
                    <div class="text-muted">Instalments allowed @if($course->min_deposit) · first payment at least {{ \App\Support\Money::format($course->min_deposit, $course->currency) }}@endif</div>
                @else
                    <div class="text-muted">Paid in full before access opens.</div>
                @endif
            </x-cb.card>
            @if($manager)
                <x-cb.card title="Danger zone" icon="ri-delete-bin-line">
                    <form method="POST" action="{{ route('academy.courses.destroy', $course) }}" onsubmit="return confirm('Delete or archive this course?')">
                        @csrf @method('DELETE')
                        <p class="small text-muted">A course with enrollments is archived instead, so its history is kept.</p>
                        <button class="btn btn-soft-danger w-100">Delete course</button>
                    </form>
                </x-cb.card>
            @endif
        </div>
    </div>

</div></div></div>
@endsection
