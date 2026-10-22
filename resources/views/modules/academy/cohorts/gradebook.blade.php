@extends('layouts.master')

@section('content')
@php $pct = fn ($v) => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 1), '0'), '.') . '%'; $course = $cohort->course; @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero :title="'Gradebook: ' . $cohort->name" icon="ri-table-line" :subtitle="$course->title" :back="route('academy.cohorts.show', $cohort)" back-label="Cohort">
        <x-slot:pills>
            <span class="cb-meta-pill">Pass grade {{ $course->pass_grade }}%</span>
            <span class="cb-meta-pill">{{ $columns->count() }} assessments</span>
            <span class="cb-meta-pill">{{ $course->certificate_enabled ? 'Certificate on completion' : 'No certificate' }}</span>
        </x-slot:pills>
    </x-cb.hero>

    @include('partials.flash')

    <x-cb.card title="Students" icon="ri-team-line" :count="$enrollments->count()" :flush="true">
        @if($enrollments->isEmpty() || $columns->isEmpty())
            <div class="empty-state"><i class="ri-table-line"></i><h6>{{ $columns->isEmpty() ? 'No published quizzes or assignments' : 'No students with course access' }}</h6>
                @if($columns->isEmpty())<p><a href="{{ route('academy.content.index', $course) }}">Add them on the course content page</a></p>@endif</div>
        @else
            <div class="table-responsive"><table class="cb-table">
                <thead><tr>
                    <th>Student</th>
                    @foreach($columns as $c)<th class="num"><a href="{{ route('academy.assessments.results', [$course, $c]) }}" class="text-reset" title="{{ $c->title }}">{{ \Illuminate\Support\Str::limit($c->title, 18) }}</a><div class="small text-muted fw-normal">{{ $c->weight + 0 }}</div></th>@endforeach
                    <th class="num">Grade</th><th>Progress</th><th>Status</th><th>Certificate</th>
                </tr></thead>
                <tbody>
                @foreach($enrollments as $e)
                    @php $r = $results[$e->id]; $cert = $e->certificate; @endphp
                    <tr>
                        <td class="fw-semibold">{{ $e->student->user->name }}<div class="small text-muted">{{ $e->student->student_no }}</div></td>
                        @foreach($columns as $c)<td class="num {{ $r[$c->id] === null ? 'text-muted' : ($r[$c->id] >= $c->pass_mark ? 'text-success' : 'text-danger') }}">{{ $pct($r[$c->id]) }}</td>@endforeach
                        <td class="num fw-semibold {{ $e->grade_percent === null ? '' : ($e->grade_percent >= $course->pass_grade ? 'text-success' : 'text-danger') }}">{{ $pct($e->grade_percent) }}</td>
                        <td>{{ $e->progress_percent }}%</td>
                        <td>@include('modules.academy.partials.pill', ['status' => $e->status, 'label' => $e->status_label])</td>
                        <td>
                            @if($cert && $cert->status === 'issued')<a href="{{ route('certificates.show', $cert) }}">{{ $cert->serial }}</a>
                            @elseif($e->status === 'completed' && $course->certificate_enabled)
                                @can('Manage certificates')<a href="{{ route('certificates.index') }}#waiting" class="small">Not issued</a>@else<span class="small text-muted">Not issued</span>@endcan
                            @else — @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
