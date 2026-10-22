@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $d = fn ($n) => rtrim(rtrim(number_format((float) $n, 1), '0'), '.'); @endphp
    <x-cb.hero title="My leave" icon="ri-suitcase-line" subtitle="Ask for time off and see what you have left this year.">
        <x-slot:actions>
            <a href="{{ route('me.payslips') }}" class="cb-hero-btn"><i class="ri-file-list-3-line"></i>My payslips</a>
        </x-slot:actions>
    </x-cb.hero>

    @include('partials.flash')

    @if($team->isNotEmpty())
        <x-cb.card title="Your team's requests" icon="ri-team-line" :count="$team->count()" :flush="true">
            <ul class="list-group list-group-flush">
                @foreach($team as $l)
                    <li class="list-group-item d-flex justify-content-between gap-2 small">
                        <span><a href="{{ route('hr.leave.show', $l) }}" class="fw-semibold">{{ $l->employee->user->name }}</a> · {{ $l->type->name }}, {{ $l->starts_on->format('j M') }} – {{ $l->ends_on->format('j M Y') }} ({{ $d($l->days) }} days)</span>
                        <a href="{{ route('hr.leave.show', $l) }}" class="btn btn-sm btn-soft-primary">Decide</a>
                    </li>
                @endforeach
            </ul>
        </x-cb.card>
    @endif

    @if(!$employee)
        <div class="empty-state"><i class="ri-suitcase-line"></i><h6>You don't have an employee record yet</h6><p class="small text-muted">Ask HR to add you, then you can book leave here.</p></div>
    @else
    <div class="row g-3">
        <div class="col-xl-8">
            <div class="row g-3 mb-3">
                @foreach($balances->filter(fn ($b) => $b->allowed !== null)->take(4) as $b)
                    <div class="col-md-3 col-6"><x-cb.stat :label="$b->type->name" :value="$d($b->left)" icon="ri-suitcase-line" :accent="['teal', 'sky', 'amber', 'violet'][$loop->index]" :hint="'of ' . $b->allowed . ' days left' . ($b->pending ? ', ' . $d($b->pending) . ' waiting' : '')" /></div>
                @endforeach
            </div>

            <x-cb.card title="My requests" icon="ri-history-line" :flush="true">
                @if($requests->isEmpty())
                    <div class="empty-state"><i class="ri-suitcase-line"></i><h6>No leave booked yet</h6></div>
                @else
                    <div class="table-responsive"><table class="cb-table stack">
                        <thead><tr><th>Type</th><th>Dates</th><th class="num">Days</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach($requests as $l)
                            <tr>
                                <td data-label="Type"><a href="{{ route('hr.leave.show', $l) }}">{{ $l->type->name }}</a></td>
                                <td data-label="Dates">{{ $l->starts_on->format('j M') }} – {{ $l->ends_on->format('j M Y') }}</td>
                                <td data-label="Days" class="num">{{ $d($l->days) }}</td>
                                <td data-label="Status">@include('modules.hr._pill', ['value' => $l->status])@if($l->decision_note)<div class="small text-muted">{{ $l->decision_note }}</div>@endif</td>
                                <td class="text-end">
                                    @if($l->status === 'pending' || ($l->status === 'approved' && $l->starts_on->gt(today())))
                                        <form method="POST" action="{{ route('hr.leave.cancel', $l) }}" onsubmit="return confirm('Cancel this request?')">@csrf<button class="btn btn-sm btn-soft-secondary">Cancel</button></form>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table></div>
                @endif
            </x-cb.card>
        </div>
        <div class="col-xl-4">
            <x-cb.card title="Ask for leave" icon="ri-add-line">
                @if($employee->status !== 'active')
                    <div class="small text-muted">Your employee record isn't active.</div>
                @else
                <form method="POST" action="{{ route('me.leave.store') }}">
                    @csrf
                    <label class="form-label">Type</label>
                    <select name="leave_type_id" class="form-select mb-2" required>
                        @foreach($types as $t)
                            @php $b = $balances->firstWhere('type.id', $t->id); @endphp
                            <option value="{{ $t->id }}" @selected((int) old('leave_type_id') === $t->id)>{{ $t->name }}{{ $b && $b->left !== null ? ' (' . $d($b->left) . ' left)' : '' }}</option>
                        @endforeach
                    </select>
                    <div class="row g-2 mb-2">
                        <div class="col-6"><label class="form-label">First day</label><input type="date" name="starts_on" class="form-control" value="{{ old('starts_on') }}" required></div>
                        <div class="col-6"><label class="form-label">Last day</label><input type="date" name="ends_on" class="form-control" value="{{ old('ends_on') }}" required></div>
                    </div>
                    <textarea name="reason" class="form-control mb-2" rows="2" maxlength="500" placeholder="Reason (optional)">{{ old('reason') }}</textarea>
                    <button class="btn btn-primary w-100">Send request</button>
                    <div class="form-text">Goes to {{ $employee->manager?->name ?? 'HR' }}. Weekends and public holidays aren't counted.</div>
                </form>
                @endif
            </x-cb.card>

            @if($holidays->isNotEmpty())
                <x-cb.card title="Coming holidays" icon="ri-calendar-2-line" :flush="true">
                    <ul class="list-group list-group-flush">
                        @foreach($holidays as $h)<li class="list-group-item small d-flex justify-content-between"><span>{{ $h->name }}</span><span class="text-muted">{{ $h->date->format('D j M') }}</span></li>@endforeach
                    </ul>
                </x-cb.card>
            @endif
        </div>
    </div>
    @endif

</div></div></div>
@endsection
