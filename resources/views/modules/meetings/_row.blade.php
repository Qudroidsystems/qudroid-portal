{{-- One meeting in a list. $m --}}
@php $mine = $m->attendees->firstWhere('user_id', auth()->id()); @endphp
<li class="list-group-item d-flex gap-3 align-items-start">
    <div class="text-center flex-shrink-0" style="width: 52px">
        <div class="small text-uppercase text-muted">{{ $m->starts_at->format('M') }}</div>
        <div class="fs-4 fw-bold lh-1">{{ $m->starts_at->format('j') }}</div>
        <div class="small text-muted">{{ $m->starts_at->format('D') }}</div>
    </div>
    <div class="flex-grow-1">
        <a href="{{ route('meetings.show', $m) }}" class="fw-semibold {{ $m->isCancelled() ? 'text-decoration-line-through text-muted' : '' }}">{{ $m->title }}</a>
        <div class="small text-muted">{{ $m->starts_at->format('H:i') }}–{{ $m->ends_at->format('H:i') }}@if($m->whereLabel()) · {{ $m->whereLabel() }}@endif · {{ $m->organizer_id === auth()->id() ? 'you organise' : 'by ' . $m->organizer?->name }} · {{ $m->attendees->count() }} invited</div>
    </div>
    <div class="text-end">
        @if($m->isCancelled())<span class="badge bg-dark-subtle text-dark">Cancelled</span>@elseif($mine)@include('modules.meetings._pill', ['value' => $mine->response])@endif
    </div>
</li>
