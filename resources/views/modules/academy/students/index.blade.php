@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Students" icon="ri-graduation-cap-line" subtitle="Applicants, admitted and active students, and alumni." :back="route('academy.index')" back-label="Academy">
        <x-slot:pills>
            @foreach(\App\Models\Academy\Student::STATUSES as $k => $v)
                <a href="{{ route('academy.students.index', ['status' => $k]) }}" class="cb-meta-pill text-reset">{{ $v }}: {{ $counts[$k] ?? 0 }}</a>
            @endforeach
        </x-slot:pills>
        <x-slot:actions><a href="{{ route('academy.students.create') }}" class="cb-hero-btn"><i class="ri-user-add-line"></i>New student</a></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Students" icon="ri-graduation-cap-line" :count="$students->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name, e-mail, phone or student number"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="">Any status</option>
                @foreach(\App\Models\Academy\Student::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
            </select>
        </form>
        @if($students->isEmpty())
            <div class="empty-state"><i class="ri-graduation-cap-line"></i><h6>No students found</h6><p><a href="{{ route('academy.students.create') }}">Add a student</a></p></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Student</th><th>Number</th><th>Phone</th><th>Status</th><th class="num">Open courses</th></tr></thead>
                <tbody>
                @foreach($students as $s)
                    <tr>
                        <td data-label="Student"><a href="{{ route('academy.students.show', $s) }}" class="d-flex align-items-center gap-2 text-reset"><img src="{{ $s->user->avatar_url }}" class="rounded-circle" width="32" height="32" alt=""><span><span class="fw-semibold">{{ $s->user->name }}</span><span class="d-block small text-muted">{{ $s->user->email }}</span></span></a></td>
                        <td data-label="Number">{{ $s->student_no }}</td>
                        <td data-label="Phone">{{ $s->user->phone_number ?? '—' }}</td>
                        <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $s->status, 'label' => $s->status_label])</td>
                        <td data-label="Open courses" class="num">{{ $s->open_enrollments }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $students->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
