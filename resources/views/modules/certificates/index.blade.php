@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Certificates" icon="ri-award-line" :subtitle="$manage ? 'Every certificate issued, and completed students still waiting for one.' : 'Your course certificates.'" />

    @include('partials.flash')

    @if($manage && $waiting->isNotEmpty())
        <x-cb.card title="Completed, no certificate yet" icon="ri-time-line" :count="$waiting->count()" :flush="true" id="waiting">
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Student</th><th>Course</th><th>Grade</th><th>Why not</th><th></th></tr></thead>
                <tbody>
                @foreach($waiting as $e)
                    @php $why = app(\App\Services\Academy\CertificateService::class)->blocker($e); $low = $why && str_starts_with($why, 'The grade'); @endphp
                    <tr>
                        <td data-label="Student" class="fw-semibold">{{ $e->student->user->name }}<div class="small text-muted">{{ $e->student->student_no }}</div></td>
                        <td data-label="Course">{{ $e->course->title }}<div class="small text-muted">{{ $e->cohort->name }} · completed {{ $e->completed_at?->format('j M Y') }}</div></td>
                        <td data-label="Grade">{{ $e->grade_percent !== null ? rtrim(rtrim(number_format((float) $e->grade_percent, 1), '0'), '.') . '%' : 'No assessments' }}</td>
                        <td data-label="Why not" class="small">{{ $why ?? 'Ready to issue.' }}</td>
                        <td class="text-end">
                            <form method="POST" action="{{ route('certificates.issue', $e) }}" class="d-flex gap-1 justify-content-end">@csrf
                                @if($low)<input type="hidden" name="override" value="1"><input name="note" class="form-control form-control-sm" style="max-width: 200px" required maxlength="300" placeholder="Reason to issue anyway">@endif
                                <button class="btn btn-sm {{ $low ? 'btn-soft-warning' : 'btn-success' }}">Issue</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        </x-cb.card>
    @endif

    <x-cb.card :title="$manage ? 'Issued certificates' : 'My certificates'" icon="ri-award-line" :count="$certificates->total()" :flush="true">
        @if($manage)
            <form class="cb-toolbar" method="GET">
                <div class="cb-search"><i class="ri-search-line"></i><input name="q" value="{{ request('q') }}" placeholder="Name or serial number"></div>
                <select name="status" class="cb-select" onchange="this.form.submit()"><option value="">Any status</option><option value="issued" @selected(request('status') === 'issued')>Valid</option><option value="revoked" @selected(request('status') === 'revoked')>Revoked</option></select>
            </form>
        @endif
        @if($certificates->isEmpty())
            <div class="empty-state"><i class="ri-award-line"></i><h6>No certificates yet</h6><p>{{ $manage ? 'Certificates are issued when an enrollment is marked completed with a pass grade.' : 'Complete a course to earn one.' }}</p></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Serial</th><th>Student</th><th>Course</th><th>Issued</th><th>Status</th></tr></thead>
                <tbody>
                @foreach($certificates as $c)
                    <tr>
                        <td data-label="Serial"><a href="{{ route('certificates.show', $c) }}" class="fw-semibold">{{ $c->serial }}</a></td>
                        <td data-label="Student">{{ $c->get('name') }}</td>
                        <td data-label="Course">{{ $c->get('course') }}</td>
                        <td data-label="Issued">{{ $c->issued_at->format('j M Y') }}</td>
                        <td data-label="Status">@include('modules.academy.partials.pill', ['status' => $c->isValid() ? 'active' : 'cancelled', 'label' => $c->isValid() ? 'Valid' : 'Revoked'])</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $certificates->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
