@extends('layouts.master')
@section('title', 'Staff attendance')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Staff attendance" icon="ri-file-list-3-line" :subtitle="$month->format('F Y') . ' · ' . $workdays . ' working days so far'" :back="route('checkin.index')" back-label="Check-in">
        <x-slot:actions>
            <a href="{{ request()->fullUrlWithQuery(['format' => 'csv']) }}" class="cb-hero-btn"><i class="ri-download-2-line"></i>CSV</a>
            <a href="{{ route('checkin.places.index') }}" class="cb-hero-btn"><i class="ri-building-line"></i>Offices</a>
        </x-slot:actions>
    </x-cb.hero>
    <form class="d-flex gap-2 mb-3 flex-wrap">
        <input type="month" name="month" value="{{ $month->format('Y-m') }}" class="form-control form-control-sm" style="max-width:180px" aria-label="Month">
        <select name="place" class="form-select form-select-sm" style="max-width:220px" aria-label="Office"><option value="">All offices</option>@foreach($places as $p)<option value="{{ $p->id }}" @selected($place?->id === $p->id)>{{ $p->name }}</option>@endforeach</select>
        <button class="btn btn-sm btn-primary">Show</button>
    </form>
    @php $days = []; for ($d = $from->copy(); $d->lte($to); $d->addDay()) $days[] = $d->copy(); @endphp
    <x-cb.card :flush="true">
        @if(!$rows)
            <div class="p-4 text-center text-muted">No clock-ins this month.</div>
        @else
        <div class="table-responsive"><table class="table table-sm table-bordered align-middle mb-0 small" style="white-space:nowrap">
            <thead class="table-light"><tr><th>Name</th><th class="text-end">In</th><th class="text-end">Late</th><th>Avg. arrival</th><th class="text-end">Hours</th>
                @foreach($days as $d)<th class="text-center {{ $d->isWeekend() ? 'text-muted' : '' }}" title="{{ $d->format('l j M') }}">{{ $d->format('j') }}</th>@endforeach</tr></thead>
            <tbody>
            @foreach($rows as $r)
                <tr>
                    <td class="fw-medium">{{ $r['user']?->name }}</td>
                    <td class="text-end">{{ $r['present'] }}@if($workdays)<span class="text-muted">/{{ $workdays }}</span>@endif</td>
                    <td class="text-end {{ $r['late'] ? 'text-warning fw-semibold' : '' }}">{{ $r['late'] }}</td>
                    <td>{{ $r['avg_in'] }}</td>
                    <td class="text-end">{{ $r['hours'] }}</td>
                    @foreach($days as $d)
                        @php $x = $r['days'][$d->toDateString()] ?? null; @endphp
                        <td class="text-center {{ $x ? ($x['late'] ? 'bg-warning-subtle' : 'bg-success-subtle') : '' }}" title="{{ $x ? $x['in'] . '–' . ($x['out'] ?? '?') : '' }}">{{ $x ? ($x['late'] ? 'L' : '✓') : '' }}</td>
                    @endforeach
                </tr>
            @endforeach
            </tbody>
        </table></div>
        @endif
    </x-cb.card>
    <p class="small text-muted mt-2">✓ on time · L late. Hover a day to see clock-in and clock-out times.</p>
</div></div></div>
@endsection
