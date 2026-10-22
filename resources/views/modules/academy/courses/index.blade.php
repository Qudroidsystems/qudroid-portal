@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Courses" icon="ri-book-2-line" :subtitle="$manager ? 'Courses, fees and cohorts.' : 'Courses offered by the academy.'">
        @if($manager)
            <x-slot:actions><a href="{{ route('academy.courses.create') }}" class="cb-hero-btn"><i class="ri-add-line"></i>New course</a></x-slot:actions>
        @endif
    </x-cb.hero>

    @include('modules.academy.partials.flash')

    <x-cb.card title="Courses" icon="ri-book-2-line" :count="$courses->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Search title or code"></div>
            <select name="programme" class="cb-select" onchange="this.form.submit()">
                <option value="">All programmes</option>
                @foreach($programmes as $id => $name)<option value="{{ $id }}" @selected(request('programme') == $id)>{{ $name }}</option>@endforeach
            </select>
            @if($manager)
                <select name="status" class="cb-select" onchange="this.form.submit()">
                    <option value="">Any status</option>
                    @foreach(\App\Models\Academy\Course::STATUSES as $k => $v)<option value="{{ $k }}" @selected(request('status') === $k)>{{ $v }}</option>@endforeach
                </select>
            @endif
        </form>
        @if($courses->isEmpty())
            <div class="empty-state"><i class="ri-book-2-line"></i><h6>No courses found</h6>@if($manager)<p><a href="{{ route('academy.courses.create') }}">Create the first course</a></p>@endif</div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Course</th><th>Programme</th><th>Level / mode</th><th class="num">Fee</th>@if($manager)<th>Status</th><th class="num">Cohorts</th><th class="num">Students</th>@endif</tr></thead>
                <tbody>
                @foreach($courses as $c)
                    <tr>
                        <td data-label="Course"><a href="{{ route('academy.courses.show', $c) }}" class="fw-semibold">{{ $c->title }}</a><div class="small text-muted">{{ $c->code }}@if($c->duration_weeks) · {{ $c->duration_weeks }} weeks @endif</div></td>
                        <td data-label="Programme">{{ $c->programme?->name ?? '—' }}</td>
                        <td data-label="Level / mode">{{ $c->level_label }} · {{ $c->mode_label }}</td>
                        <td data-label="Fee" class="num">{{ \App\Support\Money::format($c->fee, $c->currency) }}</td>
                        @if($manager)
                            <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $c->status, 'label' => \App\Models\Academy\Course::STATUSES[$c->status] ?? $c->status])</td>
                            <td data-label="Cohorts" class="num">{{ $c->cohorts_count }}</td>
                            <td data-label="Students" class="num">{{ $c->active_enrollments }}</td>
                        @endif
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $courses->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
