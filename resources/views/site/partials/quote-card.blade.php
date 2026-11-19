{{-- A testimonial on the wall. Needs $t (Testimonial); optional $profiles (student_id => profile slug). --}}
@php
    $pal = [['#0071e3', '#6e3aff'], ['#ff375f', '#ff9f0a'], ['#30d158', '#0a84ff'], ['#bf5af2', '#5e5ce6']];
    [$c1, $c2] = $pal[$t->id % count($pal)];
    $slug = isset($profiles) && $t->student_id ? ($profiles[$t->student_id] ?? null) : null;
@endphp
<figure class="sc-note {{ $t->featured ? 'is-featured' : '' }}">
    @if($t->rating)<div class="sc-stars" aria-label="{{ $t->rating }} out of 5">{{ str_repeat('★', $t->rating) }}<span style="opacity:.25">{{ str_repeat('★', 5 - $t->rating) }}</span></div>@endif
    <p>“{{ $t->quote }}”</p>
    <footer>
        <span class="sc-avatar {{ $t->kind === 'client' && $t->organisation ? 'is-logo' : '' }}" style="--c1: {{ $c1 }}; --c2: {{ $c2 }}">@if($t->photoUrl())<img src="{{ $t->photoUrl() }}" alt="" loading="lazy">@else{{ $t->initials() }}@endif</span>
        <span>
            <b>{{ $t->name }}</b>
            @if($t->byline())<small>{{ $t->byline() }}</small>@endif
            @if($slug)<a href="{{ route('site.students.show', $slug) }}">See {{ \Illuminate\Support\Str::before($t->name, ' ') }}’s projects</a>
            @elseif($t->website_url)<a href="{{ $t->website_url }}" target="_blank" rel="noopener nofollow">{{ parse_url($t->website_url, PHP_URL_HOST) }}</a>@endif
            @if($t->video_url)<a href="{{ $t->video_url }}" target="_blank" rel="noopener" style="margin-left:6px"><i class="ri-play-circle-line"></i> Watch</a>@endif
        </span>
    </footer>
</figure>
