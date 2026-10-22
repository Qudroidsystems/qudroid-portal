@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Applications" icon="ri-user-search-line" subtitle="Online applications from the public Apply page and from students." :back="route('academy.index')" back-label="Academy">
        <x-slot:pills>
            @foreach(\App\Models\Academy\Application::STATUSES as $k => $v)
                <a href="{{ route('academy.applications.index', ['status' => $k]) }}" class="cb-meta-pill text-reset">{{ $v }}: {{ $counts[$k] ?? 0 }}</a>
            @endforeach
        </x-slot:pills>
        <x-slot:actions><a href="{{ route('apply.index') }}" target="_blank" class="cb-hero-btn"><i class="ri-external-link-line"></i>Public Apply page</a></x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="{{ \App\Models\Academy\Application::STATUSES[$status] ?? 'All applications' }}" icon="ri-file-list-3-line" :count="$applications->total()" :flush="true">
        <form class="cb-toolbar" method="GET">
            <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name, e-mail, phone or reference"></div>
            <select name="status" class="cb-select" onchange="this.form.submit()">
                <option value="all" @selected($status === 'all')>Any status</option>
                @foreach(\App\Models\Academy\Application::STATUSES as $k => $v)<option value="{{ $k }}" @selected($status === $k)>{{ $v }}</option>@endforeach
            </select>
            <select name="course_id" class="cb-select" onchange="this.form.submit()">
                <option value="">Any course</option>
                @foreach($courses as $id => $title)<option value="{{ $id }}" @selected((int) request('course_id') === $id)>{{ $title }}</option>@endforeach
            </select>
        </form>
        @if($applications->isEmpty())
            <div class="empty-state"><i class="ri-inbox-line"></i><h6>No applications</h6><p>Share the <a href="{{ route('apply.index') }}" target="_blank">Apply page</a> to receive applications.</p></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Applicant</th><th>Course</th><th>Experience</th><th>Received</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($applications as $a)
                    <tr>
                        <td data-label="Applicant"><a href="{{ route('academy.applications.show', $a) }}" class="text-reset"><span class="fw-semibold">{{ $a->student->user->name }}</span><span class="d-block small text-muted">{{ $a->reference }} · {{ $a->student->user->email }}</span></a></td>
                        <td data-label="Course">{{ $a->course->title }}<div class="small text-muted">{{ $a->cohort->name }}</div></td>
                        <td data-label="Experience">{{ \App\Models\Academy\Application::EXPERIENCE[$a->experience_level] ?? '—' }}@if($a->has_laptop === false)<div class="small text-warning">No laptop</div>@endif</td>
                        <td data-label="Received">{{ $a->created_at->format('j M Y') }}<div class="small text-muted">{{ $a->created_at->diffForHumans() }}</div></td>
                        <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $a->status, 'label' => $a->status_label])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $applications->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
