@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Academy" icon="ri-book-open-line" subtitle="Admissions, courses, cohorts and enrollments for Qudroid Coding Academy.">
        <x-slot:actions>
            <a href="{{ route('academy.students.create') }}" class="cb-hero-btn"><i class="ri-user-add-line"></i>New student</a>
            <a href="{{ route('academy.courses.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New course</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <div class="row g-3 mb-3">
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Active students" :value="number_format($students['active'] ?? 0)" icon="ri-graduation-cap-line" accent="teal" :hint="($students['admitted'] ?? 0) . ' admitted, not yet started'" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Applicants to review" :value="number_format($students['applicant'] ?? 0)" icon="ri-user-search-line" accent="amber" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Published courses" :value="$courses" icon="ri-book-2-line" accent="sky" :hint="$draftCourses ? $draftCourses . ' in draft' : null" /></div>
        <div class="col-xl-3 col-md-6"><x-cb.stat label="Awaiting payment" :value="number_format($pending)" icon="ri-time-line" accent="rose" :hint="$active . ' active enrollments'" /></div>
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Cohorts taking students" icon="ri-team-line" :count="$openCohorts->count()" :flush="true">
                <x-slot:tools><a href="{{ route('academy.courses.index') }}" class="btn btn-sm btn-soft-primary">All courses</a></x-slot:tools>
                @if($openCohorts->isEmpty())
                    <div class="empty-state"><i class="ri-calendar-event-line"></i><h6>No open cohorts</h6><p>Open a cohort from a course page to start taking registrations.</p></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Cohort</th><th>Starts</th><th>Instructor</th><th class="num">Seats</th></tr></thead>
                        <tbody>
                        @foreach($openCohorts as $c)
                            <tr>
                                <td data-label="Cohort"><a href="{{ route('academy.cohorts.show', $c) }}" class="fw-semibold">{{ $c->name }}</a><div class="small text-muted">{{ $c->course->title }}</div></td>
                                <td data-label="Starts">{{ $c->starts_on?->format('j M Y') ?? '—' }}</td>
                                <td data-label="Instructor">{{ $c->instructor?->name ?? '—' }}</td>
                                <td data-label="Seats" class="num">{{ $c->seats }}{{ $c->capacity ? ' / ' . $c->capacity : '' }}</td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>

            <x-cb.card title="Latest enrollments" icon="ri-file-list-3-line" :flush="true">
                <x-slot:tools><a href="{{ route('academy.enrollments.index') }}" class="btn btn-sm btn-soft-primary">All enrollments</a></x-slot:tools>
                @if($recent->isEmpty())
                    <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No enrollments yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <tbody>
                        @foreach($recent as $e)
                            <tr>
                                <td data-label="Student"><a href="{{ route('academy.students.show', $e->student_id) }}" class="fw-semibold">{{ $e->student->user->name }}</a><div class="small text-muted">{{ $e->course->title }} · {{ $e->cohort->name }}</div></td>
                                <td data-label="Status" class="text-end">@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])<div class="small text-muted">{{ $e->enrolled_at?->diffForHumans() }}</div></td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>

        <div class="col-xl-5">
            <x-cb.card title="Applicants" icon="ri-user-search-line" :count="$students['applicant'] ?? 0" :flush="true">
                <x-slot:tools><a href="{{ route('academy.students.index', ['status' => 'applicant']) }}" class="btn btn-sm btn-soft-primary">Review</a></x-slot:tools>
                @if($applicants->isEmpty())
                    <div class="empty-state"><i class="ri-inbox-line"></i><h6>Nobody waiting</h6><p>New applications will show here.</p></div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($applicants as $s)
                            <a href="{{ route('academy.students.show', $s) }}" class="list-group-item list-group-item-action d-flex justify-content-between">
                                <span><strong>{{ $s->user->name }}</strong><span class="d-block small text-muted">{{ $s->student_no }} · {{ $s->user->email }}</span></span>
                                <small class="text-muted">{{ $s->created_at->diffForHumans() }}</small>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-cb.card>
        </div>
    </div>

</div></div></div>
@endsection
