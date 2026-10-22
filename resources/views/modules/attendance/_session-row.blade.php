{{-- One session in a list: $s (with cohort.course) --}}
<a href="{{ route('attendance.register', $s) }}" class="list-group-item list-group-item-action d-flex justify-content-between gap-2">
    <span><strong>{{ $s->cohort->name }}</strong><span class="d-block small text-muted">{{ $s->cohort->course->title }}@if($s->topic) · {{ $s->topic }}@endif</span></span>
    <span class="text-end small text-nowrap">{{ $s->date->format('D j M') }}@if($s->timeRange())<span class="d-block text-muted">{{ $s->timeRange() }}</span>@endif</span>
</a>
