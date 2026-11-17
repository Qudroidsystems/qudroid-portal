{{--
    The conversation on a piece of work. Needs: $comments, $action (where a new comment posts),
    $studentId (user id of the student, to label who is who). Optional: $lineInputId (a code line box),
    $questions (for quiz attempts: id => number), $title.
--}}
@php $lineInputId = $lineInputId ?? null; $questions = $questions ?? collect(); @endphp
<div class="qd-comments" id="comments">
    <div class="fw-semibold mb-2"><i class="ri-chat-3-line me-1"></i>{{ $title ?? 'Comments' }} @if($comments->count())<span class="text-muted fw-normal">({{ $comments->count() }})</span>@endif</div>
    @forelse($comments as $c)
        @php $mine = $c->user_id === auth()->id(); $isStudent = $c->user_id === $studentId; @endphp
        <div class="d-flex gap-2 mb-2 {{ $mine ? 'flex-row-reverse text-end' : '' }}">
            <img src="{{ $c->user?->avatar_url }}" class="rounded-circle flex-shrink-0" width="28" height="28" alt="">
            <div class="px-3 py-2 rounded-3 {{ $isStudent ? 'bg-light' : 'bg-primary-subtle' }}" style="max-width: 85%; text-align: left">
                <div class="small text-muted">{{ $c->user?->name }} · {{ $isStudent ? 'Student' : 'Instructor' }} · {{ $c->created_at->diffForHumans() }}
                    @if($c->line)<span class="badge bg-warning-subtle text-warning ms-1">Line {{ $c->line }}</span>@endif
                    @if($c->question_id && $questions->has($c->question_id))<span class="badge bg-info-subtle text-info ms-1">Question {{ $questions[$c->question_id] }}</span>@endif
                </div>
                <div style="white-space: pre-wrap">{{ $c->body }}</div>
            </div>
        </div>
    @empty
        <p class="text-muted small mb-2">No comments yet.</p>
    @endforelse
    <form method="POST" action="{{ $action }}" class="mt-2">
        @csrf
        <div class="d-flex gap-2 align-items-start">
            @if($lineInputId)
                <input type="number" min="1" name="line" id="{{ $lineInputId }}" class="form-control form-control-sm" style="width: 84px" placeholder="Line" title="The line of code this is about (optional)">
            @endif
            @if($questions->isNotEmpty())
                <select name="question_id" class="form-select form-select-sm" style="width: 130px"><option value="">Whole quiz</option>@foreach($questions as $qid => $n)<option value="{{ $qid }}">Question {{ $n }}</option>@endforeach</select>
            @endif
            <textarea name="body" class="form-control form-control-sm" rows="1" maxlength="5000" required placeholder="Write a comment…"></textarea>
            <button class="btn btn-sm btn-primary"><i class="ri-send-plane-2-line"></i></button>
        </div>
    </form>
</div>
