@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Enrollments" icon="ri-file-list-3-line" subtitle="Every student's place on every cohort, with fee and access." :back="route('academy.index')" back-label="Academy">
        <x-slot:actions><a href="{{ route('academy.enrollments.index', ['access' => 'locked']) }}" class="cb-hero-btn"><i class="ri-lock-line"></i>Awaiting access</a></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Enrollments" icon="ri-file-list-3-line" :count="$enrollments->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Student name or number"></div>
            <select name="course" class="cb-select" onchange="this.form.submit()">
                <option value="">All courses</option>
                @foreach($courses as $id => $t)<option value="{{ $id }}" @selected(request('course') == $id)>{{ $t }}</option>@endforeach
            </select>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach(\App\Models\Academy\Enrollment::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
            @if(request('access'))<input type="hidden" name="access" value="{{ request('access') }}"><a href="{{ route('academy.enrollments.index') }}" class="btn btn-sm btn-light">Clear filters</a>@endif
        </form>
        @if($enrollments->isEmpty())
            <div class="empty-state"><i class="ri-file-list-3-line"></i><h6>No enrollments found</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Student</th><th>Course / cohort</th><th>Enrolled</th><th>Status</th><th>Access</th><th class="num">Fee</th></tr></thead>
                <tbody>
                @foreach($enrollments as $e)
                    <tr>
                        <td data-label="Student"><a href="{{ route('academy.students.show', $e->student_id) }}" class="fw-semibold">{{ $e->student->user->name }}</a><div class="small text-muted">{{ $e->student->student_no }}</div></td>
                        <td data-label="Course"><a href="{{ route('academy.cohorts.show', $e->cohort_id) }}">{{ $e->course->title }}</a><div class="small text-muted">{{ $e->cohort->name }}</div></td>
                        <td data-label="Enrolled">{{ $e->enrolled_at?->format('j M Y') }}</td>
                        <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])</td>
                        <td data-label="Access">@if($e->access_granted)<span class="text-success"><i class="ri-lock-unlock-line"></i> Open</span>@else<span class="text-muted"><i class="ri-lock-line"></i> Locked</span>@endif</td>
                        <td data-label="Fee" class="num">{{ \App\Support\Money::format($e->netFee(), $e->currency) }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $enrollments->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
