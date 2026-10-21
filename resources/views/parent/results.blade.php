{{-- resources/views/parent/results.blade.php — what shows is set under Admin → Result display --}}
@extends('layouts.master')

@section('content')
@php
    $naira = fn ($v) => '₦' . number_format((float) $v, 2);
    $fmt = fn ($v, $d = 1) => ($v === null || $v === '') ? '—' : (is_numeric($v) ? rtrim(rtrim(number_format((float) $v, $d, '.', ''), '0'), '.') : $v);
    $d = $result['display'] ?? ['subject' => [], 'summary' => []];
    $S = fn ($k) => !empty($d['subject'][$k]);
    $M = fn ($k) => !empty($d['summary'][$k]);
    $sum = $result['summary'] ?? [];
    $subjects = collect($result['subjects'] ?? []);
    $partNames = $subjects->flatMap(fn ($r) => collect($r['assessments'] ?? [])->pluck('name'))->unique()->values();
@endphp
<div class="main-content">
<div class="page-content">
<div class="container-fluid">
    <x-cb.hero :title="'Results — ' . $child->firstname . ' ' . $child->lastname" icon="ri-file-chart-line"
               subtitle="Term results and report cards." :back="route('parent.dashboard')" back-label="My children" />

    @include('parent.partials.nav', ['section' => 'results'])

    @if($terms->isEmpty())
        <div class="cb-card"><div class="empty-state"><i class="ri-file-chart-line"></i><h6>No results yet</h6><p>Results appear here once the school publishes them.</p></div></div>
    @else
        <div class="row g-3">
            <div class="col-lg-3">
                <x-cb.card title="Terms" icon="ri-calendar-line" :count="$terms->count()" :flush="true">
                    <div class="list-group list-group-flush">
                        @foreach($terms as $t)
                            @php $active = $selected && $t->term_id == $selected->term_id && $t->session_id == $selected->session_id; @endphp
                            <a href="{{ route('parent.results', ['student' => $child->id, 'term_id' => $t->term_id, 'session_id' => $t->session_id]) }}"
                               class="list-group-item list-group-item-action {{ $active ? 'active' : '' }}">
                                <div class="d-flex justify-content-between"><strong>{{ $t->term }} · {{ $t->session }}</strong>
                                    @if(!$t->allowed && !empty($t->not_released))<span class="status-pill st-muted">Not released</span>
                                    @elseif(!$t->allowed)<span class="status-pill st-danger">On hold</span>
                                    @elseif(!$t->ready)<span class="status-pill st-pending">In progress</span>
                                    @else<span class="status-pill st-paid">Ready</span>@endif
                                </div>
                                <div class="small {{ $active ? '' : 'text-muted' }}">{{ $t->class_name }} · {{ $t->subjects }} subjects</div>
                            </a>
                        @endforeach
                    </div>
                </x-cb.card>
            </div>
            <div class="col-lg-9">
                @if($selected && !$selected->allowed)
                    <div class="cb-card"><div class="empty-state"><i class="ri-lock-line"></i><h6>{{ !empty($selected->not_released) ? 'Not released yet' : 'This result is on hold' }}</h6>
                        <p>{{ $selected->hold_message ?: 'Please clear the outstanding fees to view this result.' }}</p>
                        @if($selected->owed > 0)
                            <p class="fw-bold text-danger">Outstanding: {{ $naira($selected->owed) }}</p>
                            <a href="{{ route('parent.pay', $child->id) }}" class="action-btn btn-primary-cb"><i class="ri-secure-payment-line"></i>Pay online</a>
                        @endif
                    </div></div>
                @elseif($selected && $result)
                    {{-- Overall --}}
                    @php
                        $tiles = array_filter([
                            $M('total_score') && isset($sum['term_total']) ? ['Total obtained', $fmt($sum['term_total']), 'ri-sum'] : null,
                            $M('average') && isset($sum['term_average']) ? ['Term average', $fmt($sum['term_average']) . '%', 'ri-percent-line'] : null,
                            $M('cum_overall') && isset($sum['cum_average']) ? ['Cumulative average', $fmt($sum['cum_average']) . '%', 'ri-line-chart-line'] : null,
                            $M('class_position') && !empty($sum['class_position_label']) ? ['Position in class', $sum['class_position_label'] . ($M('class_size') && !empty($sum['class_size']) ? ' of ' . $sum['class_size'] : ''), 'ri-trophy-line'] : null,
                            $M('arm_position') && !empty($sum['arm_position_label']) ? ['Position in arm', $sum['arm_position_label'] . ($M('class_size') && !empty($sum['arm_size']) ? ' of ' . $sum['arm_size'] : ''), 'ri-medal-line'] : null,
                            $M('gpa') && isset($sum['gpa']) ? ['GPA', $fmt($sum['gpa'], 2), 'ri-star-line'] : null,
                            $M('attendance') && isset($result['times_absent']) && $result['times_absent'] !== null ? ['Times absent', $result['times_absent'], 'ri-calendar-close-line'] : null,
                        ]);
                    @endphp
                    @if($tiles)
                        <div class="row g-2 mb-3">
                            @foreach($tiles as [$label, $value, $icon])
                                <div class="col-6 col-md-4 col-xl">
                                    <div class="cb-card h-100 p-3 mb-0">
                                        <div class="small text-muted"><i class="{{ $icon }}"></i> {{ $label }}</div>
                                        <div class="fs-4 fw-bold">{{ $value }}</div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    <x-cb.card :title="$selected->term . ' · ' . $selected->session . ' · ' . $selected->class_name" icon="ri-file-list-3-line" :count="$subjects->count()" :flush="true">
                        <x-slot:tools>
                            @if($selected->ready && !empty($result['report_card_url']))
                                <a target="_blank" class="action-btn btn-go"
                                   href="{{ route('parent.report-card', ['student' => $child->id, 'session' => $selected->session_id, 'term' => $selected->term_id, 'class' => $selected->class_id]) }}">
                                    <i class="ri-file-pdf-2-line"></i>Report card (PDF)</a>
                            @endif
                        </x-slot:tools>
                        @unless($selected->ready)
                            <div class="cb-banner info m-3"><i class="ri-information-line"></i><div>Some subjects are still being checked by teachers. The report card will be available once all are approved.</div></div>
                        @endunless
                        <div class="table-responsive">
                            <table class="table table-hover align-middle mb-0">
                                <thead><tr>
                                    <th>Subject</th>
                                    @if($S('assessments'))@foreach($partNames as $pn)<th class="text-end small">{{ $pn }}</th>@endforeach @endif
                                    @if($S('total'))<th class="text-end">Total</th>@endif
                                    @if($S('bf'))<th class="text-end">BF</th>@endif
                                    @if($S('cum'))<th class="text-end">Cum</th>@endif
                                    @if($S('cum_average'))<th class="text-end">Cum avg</th>@endif
                                    @if($S('grade'))<th>Grade</th>@endif
                                    @if($S('position'))<th class="text-end">Position</th>@endif
                                    @if($S('position_total'))<th class="text-end">Pos (total)</th>@endif
                                    @if($S('arm_position'))<th class="text-end">Arm pos</th>@endif
                                    @if($S('arm_position_cum'))<th class="text-end">Arm pos (cum)</th>@endif
                                    @if($S('class_average'))<th class="text-end">Class avg</th>@endif
                                    @if($S('highest_lowest'))<th class="text-end">High / Low</th>@endif
                                    @if($S('remark'))<th>Remark</th>@endif
                                </tr></thead>
                                <tbody>
                                @foreach($subjects as $r)
                                    @php $parts = collect($r['assessments'] ?? [])->keyBy('name'); @endphp
                                    <tr>
                                        <td class="fw-semibold">{{ $r['subject'] }}</td>
                                        @if($S('assessments'))@foreach($partNames as $pn)<td class="text-end text-muted">{{ $fmt($parts[$pn]['score'] ?? null) }}</td>@endforeach @endif
                                        @if($S('total'))<td class="text-end fw-bold">{{ $fmt($r['total'] ?? null) }}</td>@endif
                                        @if($S('bf'))<td class="text-end">{{ $fmt($r['bf'] ?? null) }}</td>@endif
                                        @if($S('cum'))<td class="text-end">{{ $fmt($r['cum'] ?? null) }}</td>@endif
                                        @if($S('cum_average'))<td class="text-end">{{ $fmt($r['cum_average'] ?? null) }}</td>@endif
                                        @if($S('grade'))<td><span class="term-badge">{{ ($r['grade'] ?? null) ?: '—' }}</span></td>@endif
                                        @if($S('position'))<td class="text-end">{{ $r['position_label'] ?? '—' }}</td>@endif
                                        @if($S('position_total'))<td class="text-end">{{ $r['position_total'] ?? '—' }}</td>@endif
                                        @if($S('arm_position'))<td class="text-end">{{ $r['arm_position'] ?? '—' }}</td>@endif
                                        @if($S('arm_position_cum'))<td class="text-end">{{ $r['arm_position_cum'] ?? '—' }}</td>@endif
                                        @if($S('class_average'))<td class="text-end">{{ $fmt($r['class_average'] ?? null) }}</td>@endif
                                        @if($S('highest_lowest'))<td class="text-end small">{{ $fmt($r['highest'] ?? null) }} / {{ $fmt($r['lowest'] ?? null) }}</td>@endif
                                        @if($S('remark'))<td class="small text-muted">{{ $r['remark'] ?? '' }}</td>@endif
                                    </tr>
                                @endforeach
                                </tbody>
                            </table>
                        </div>
                    </x-cb.card>

                    @if($M('comments') && (!empty($result['class_teacher_comment']) || !empty($result['principal_comment'])))
                        <x-cb.card title="Comments" icon="ri-chat-quote-line">
                            @if(!empty($result['class_teacher_comment']))<p class="mb-2"><b>Class teacher:</b> {{ $result['class_teacher_comment'] }}</p>@endif
                            @if(!empty($result['principal_comment']))<p class="mb-0"><b>Principal:</b> {{ $result['principal_comment'] }}</p>@endif
                        </x-cb.card>
                    @endif
                @endif
            </div>
        </div>
    @endif
</div>
</div>
</div>
@endsection
