{{-- resources/views/admin/result-display/index.blade.php --}}
@extends('layouts.master')

@section('content')
@php
    $on = fn ($group, $key) => old($group . '.' . $key, $s[$group][$key] ?? false);
    $sample = [
        ['English Language', [12, 14, 13, 48], 87, 'A1', 'Excellent', '2nd', 64.2],
        ['Mathematics', [10, 11, 15, 41], 77, 'A1', 'Excellent', '5th', 58.9],
        ['Basic Science', [9, 12, 10, 35], 66, 'B2', 'Very good', '11th', 61.4],
    ];
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <x-cb.hero title="Result display" icon="ri-layout-grid-line"
               subtitle="Choose what parents and students see on the portal's result pages and in the mobile app.">
        <x-slot:pills>
            <span class="cb-meta-pill"><i class="ri-parent-line"></i>Parents: {{ $s['parents_can_view'] ? 'can view' : 'hidden' }}</span>
            <span class="cb-meta-pill"><i class="ri-user-3-line"></i>Students: {{ $s['students_can_view'] ? 'can view' : 'hidden' }}</span>
            <span class="cb-meta-pill"><i class="ri-trophy-line"></i>Positions by {{ $s['position_basis'] === 'term' ? 'term average' : 'cumulative average' }}</span>
        </x-slot:pills>
    </x-cb.hero>

    @if(session('success'))<div class="cb-banner info"><i class="ri-checkbox-circle-line"></i><div>{{ session('success') }}</div></div>@endif
    @if($errors->any())<div class="cb-banner warning"><i class="ri-error-warning-line"></i><div>{{ $errors->first() }}</div></div>@endif

    <form method="POST" action="{{ route('result-display.save') }}">@csrf
        <div class="row g-3">
            <div class="col-xl-7">
                <x-cb.card title="Who can see results" icon="ri-eye-line">
                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch" name="parents_can_view" value="1" id="pcv" @checked(old('parents_can_view', $s['parents_can_view']))>
                        <label class="form-check-label fw-semibold" for="pcv">Parents can view their children's results</label>
                    </div>
                    <div class="form-check form-switch">
                        <input class="form-check-input" type="checkbox" role="switch" name="students_can_view" value="1" id="scv" @checked(old('students_can_view', $s['students_can_view']))>
                        <label class="form-check-label fw-semibold" for="scv">Students can view their own results</label>
                    </div>
                    <div class="form-text mt-2">Applies to the portal and the mobile app (results, report card, progress chart and AI guide). Staff are not affected.
                        @if($model === 'dynamic') Results held for unpaid fees stay held (Result access settings). @endif</div>
                </x-cb.card>

                <x-cb.card title="For each subject" icon="ri-book-2-line">
                    <div class="row g-2">
                        @foreach($catalogue['subject'] as $key => [$label, $default, $hint])
                            <div class="col-md-6">
                                <label class="d-flex gap-2 align-items-start border rounded-3 p-2 h-100" style="cursor:pointer">
                                    <input class="form-check-input mt-1 rd-toggle" type="checkbox" name="subject[{{ $key }}]" value="1" data-key="s-{{ $key }}" @checked($on('subject', $key))>
                                    <span><span class="fw-semibold d-block">{{ $label }}</span>@if($hint)<span class="small text-muted">{{ $hint }}</span>@endif</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                </x-cb.card>

                <x-cb.card title="Overall result" icon="ri-trophy-line">
                    <div class="row g-2">
                        @foreach($catalogue['summary'] as $key => [$label, $default, $hint])
                            <div class="col-md-6">
                                <label class="d-flex gap-2 align-items-start border rounded-3 p-2 h-100" style="cursor:pointer">
                                    <input class="form-check-input mt-1 rd-toggle" type="checkbox" name="summary[{{ $key }}]" value="1" data-key="m-{{ $key }}" @checked($on('summary', $key))>
                                    <span><span class="fw-semibold d-block">{{ $label }}</span>@if($hint)<span class="small text-muted">{{ $hint }}</span>@endif</span>
                                </label>
                            </div>
                        @endforeach
                    </div>
                    <div class="row g-2 mt-2">
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Overall positions are worked out from</label>
                            <select name="position_basis" class="form-select">
                                <option value="cum" @selected(old('position_basis', $s['position_basis']) === 'cum')>Cumulative average (all terms so far this session)</option>
                                <option value="term" @selected(old('position_basis', $s['position_basis']) === 'term')>This term's average only</option>
                            </select>
                            <div class="form-text">Students with equal averages share a position (1st, 2nd, 2nd, 4th), as on the broadsheet.</div>
                        </div>
                    </div>
                </x-cb.card>

                <div class="d-flex gap-2 mb-4">
                    <button class="action-btn btn-go"><i class="ri-save-3-line"></i>Save</button>
                    <a href="{{ route('result-display.index') }}" class="action-btn btn-open"><i class="ri-arrow-go-back-line"></i>Discard changes</a>
                </div>
            </div>

            <div class="col-xl-5">
                <div style="position:sticky;top:90px">
                    <x-cb.card title="Preview" icon="ri-smartphone-line">
                        <div class="small text-muted mb-2">Sample data — updates as you tick.</div>
                        <div class="rd-sum d-flex flex-wrap gap-2 mb-3">
                            <span class="rd-chip" data-show="m-total_score"><b>230</b> Total</span>
                            <span class="rd-chip" data-show="m-average"><b>76.7</b> Average</span>
                            <span class="rd-chip" data-show="m-cum_overall"><b>74.9</b> Cum. avg</span>
                            <span class="rd-chip" data-show="m-class_position"><b>3rd</b><span data-show="m-class_size"> of 84</span> in class</span>
                            <span class="rd-chip" data-show="m-arm_position"><b>1st</b><span data-show="m-class_size"> of 28</span> in arm</span>
                            @isset($catalogue['summary']['gpa'])<span class="rd-chip" data-show="m-gpa"><b>4.33</b> GPA</span>@endisset
                        </div>
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-2" style="font-size:.82rem">
                                <thead><tr>
                                    <th>Subject</th>
                                    <th data-show="s-assessments" class="text-end">{{ $model === 'fixed' ? 'CA1 · CA2 · CA3 · Exam' : 'CA · Exam' }}</th>
                                    <th data-show="s-total" class="text-end">Total</th>
                                    <th data-show="s-cum_average" class="text-end">Cum avg</th>
                                    <th data-show="s-grade">Grade</th>
                                    <th data-show="s-position" class="text-end">Pos</th>
                                    <th data-show="s-class_average" class="text-end">Class avg</th>
                                    <th data-show="s-remark">Remark</th>
                                </tr></thead>
                                <tbody>
                                @foreach($sample as [$sub, $parts, $tot, $gr, $rem, $pos, $avg])
                                    <tr>
                                        <td>{{ $sub }}</td>
                                        <td data-show="s-assessments" class="text-end text-muted">{{ implode(' · ', $parts) }}</td>
                                        <td data-show="s-total" class="text-end fw-bold">{{ $tot }}</td>
                                        <td data-show="s-cum_average" class="text-end">{{ $tot - 2 }}.5</td>
                                        <td data-show="s-grade"><span class="term-badge">{{ $gr }}</span></td>
                                        <td data-show="s-position" class="text-end">{{ $pos }}</td>
                                        <td data-show="s-class_average" class="text-end">{{ $avg }}</td>
                                        <td data-show="s-remark" class="small text-muted">{{ $rem }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                        <div data-show="m-comments" class="small border-start border-3 ps-2 mb-2"><b>Class teacher:</b> A focused and hard-working term.</div>
                        <div data-show="m-attendance" class="small text-muted mb-2"><i class="ri-calendar-close-line"></i> Absent 2 times</div>
                        <div data-show="m-report_card"><span class="action-btn btn-go" style="pointer-events:none"><i class="ri-file-pdf-2-line"></i>Report card (PDF)</span></div>
                        <div class="small text-muted mt-3">The printed report card keeps its own layout. Choose its columns when exporting under Student reports.</div>
                    </x-cb.card>
                </div>
            </div>
        </div>
    </form>
</div>
</div>
</div>

<style>
    .rd-chip { background: var(--vz-light, #f3f6f9); border-radius: 10px; padding: 6px 10px; font-size: .82rem; }
    .rd-chip b { font-size: 1rem; margin-right: 2px; }
</style>
<script>
(function () {
    function sync() {
        var state = {};
        document.querySelectorAll('.rd-toggle').forEach(function (c) { state[c.dataset.key] = c.checked; });
        document.querySelectorAll('[data-show]').forEach(function (el) {
            var k = el.getAttribute('data-show');
            el.style.display = state[k] === false ? 'none' : '';
        });
    }
    document.querySelectorAll('.rd-toggle').forEach(function (c) { c.addEventListener('change', sync); });
    sync();
})();
</script>
@endsection
