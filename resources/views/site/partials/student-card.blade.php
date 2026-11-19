{{-- One student in the showcase grid. Needs $p (StudentProfile with student.user and student.enrollments.course/cohort). --}}
@php
    $pal = [['#0071e3', '#6e3aff'], ['#ff375f', '#ff9f0a'], ['#30d158', '#0a84ff'], ['#bf5af2', '#5e5ce6'], ['#ff9f0a', '#ff375f'], ['#64d2ff', '#0071e3']];
    [$c1, $c2] = $pal[$p->id % count($pal)];
    $en = $p->student->enrollments->sortByDesc(fn ($e) => $e->status === 'completed')->first();
    $photo = $p->photoUrl();
@endphp
<a class="sc-person" href="{{ route('site.students.show', $p) }}" style="--c1: {{ $c1 }}; --c2: {{ $c2 }}">
    @if($p->featured)<span class="sc-star"><i class="ri-star-fill"></i> Featured</span>@endif
    <div class="sc-cover"></div>
    <div class="sc-photo {{ $photo ? '' : 'is-initials' }}">@if($photo)<img src="{{ $photo }}" alt="{{ $p->name() }}" loading="lazy">@else{{ $p->initials() }}@endif</div>
    <div class="sc-person-body">
        <h3>{{ $p->name() }}</h3>
        @if($p->headline)<p class="sc-headline">{{ $p->headline }}</p>@endif
        @if($en)<span class="sc-course"><i class="ri-graduation-cap-fill"></i> {{ $en->course?->title }}@if($en->cohort) · {{ $en->cohort->name }}@endif</span>@endif
        @if($p->skills)<div class="s-tags">@foreach(array_slice($p->skills, 0, 3) as $s)<span class="s-tag">{{ $s }}</span>@endforeach</div>@endif
        <div class="sc-foot">
            <span>@if($p->open_to_work)<span class="sc-badge"><i class="ri-briefcase-line"></i> Open to work</span>@else{{ $p->projects_count ?? $p->projects()->count() }} {{ \Illuminate\Support\Str::plural('project', $p->projects_count ?? 0) }}@endif</span>
            <span class="sc-links">@foreach(array_slice($p->linkList(), 0, 3) as [$k, $label, $icon])<i class="{{ $icon }}" title="{{ $label }}"></i>@endforeach</span>
        </div>
    </div>
</a>
