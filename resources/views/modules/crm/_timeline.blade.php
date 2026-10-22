{{-- Timeline for a company, contact or deal. Needs $activities, $subjectType ('company'|'contact'|'deal'), $subjectId, $staff. --}}
<x-cb.card title="Timeline" icon="ri-chat-history-line" :count="$activities->count()">
    <form method="POST" action="{{ route('crm.activities.store', [$subjectType, $subjectId]) }}" class="mb-3 crm-log">
        @csrf
        <div class="d-flex flex-wrap gap-2 mb-2">
            @foreach(\App\Models\Crm\Activity::LOGGABLE as $t)
                <input type="radio" class="btn-check" name="type" value="{{ $t }}" id="t-{{ $t }}" @checked(old('type', 'note') === $t)>
                <label class="btn btn-sm btn-outline-secondary" for="t-{{ $t }}"><i class="{{ \App\Models\Crm\Activity::TYPES[$t][1] }}"></i> {{ \App\Models\Crm\Activity::TYPES[$t][0] }}</label>
            @endforeach
        </div>
        <textarea name="body" rows="2" class="form-control mb-2" maxlength="5000" required placeholder="What happened, or what needs doing">{{ old('body') }}</textarea>
        <div class="d-flex flex-wrap gap-2 align-items-center crm-fu">
            <input type="datetime-local" name="due_at" class="form-control form-control-sm" style="max-width: 14rem" value="{{ old('due_at') }}" title="Due (follow-ups)">
            <select name="assigned_to" class="form-select form-select-sm" style="max-width: 14rem" title="For (follow-ups)">
                <option value="">For me</option>
                @foreach($staff as $s)@if($s->id !== auth()->id())<option value="{{ $s->id }}">{{ $s->name }}</option>@endif @endforeach
            </select>
            <button class="btn btn-sm btn-primary ms-auto">Add</button>
        </div>
        <div class="form-text">The date and person apply to follow-ups.</div>
    </form>

    @forelse($activities as $a)
        <div class="d-flex gap-2 py-2 border-top">
            <div class="fs-5 {{ $a->type === 'stage' ? 'text-muted' : 'text-primary' }}"><i class="{{ $a->icon }}"></i></div>
            <div class="flex-grow-1">
                <div class="small text-muted">
                    <span class="fw-semibold text-body">{{ $a->type_label }}</span> · {{ $a->user?->name ?? 'Someone' }} · {{ $a->created_at->format('j M Y H:i') }}
                    @if(($showSubject ?? false) && $a->subject && !($a->subject instanceof \App\Models\Crm\Company))
                        · on <a href="{{ app(\App\Services\Crm\CrmService::class)->link($a) }}">{{ $a->subject->title ?? $a->subject->name }}</a>
                    @endif
                </div>
                <div style="white-space: pre-line" class="{{ $a->done_at ? 'text-decoration-line-through text-muted' : '' }}">{{ $a->body }}</div>
                @if($a->type === 'follow_up')
                    <div class="small {{ $a->isOverdue() ? 'text-danger fw-semibold' : 'text-muted' }}">Due {{ $a->due_at?->format('D j M, g:ia') }} · {{ $a->assignee?->name ?? '—' }}@if($a->done_at) · done {{ $a->done_at->format('j M') }}@endif</div>
                @endif
            </div>
            <div class="text-nowrap">
                @if($a->type === 'follow_up')
                    <form method="POST" action="{{ route('crm.activities.complete', $a) }}" class="d-inline">@csrf<button class="btn btn-sm {{ $a->done_at ? 'btn-soft-secondary' : 'btn-soft-success' }}" title="{{ $a->done_at ? 'Not done' : 'Done' }}"><i class="{{ $a->done_at ? 'ri-arrow-go-back-line' : 'ri-check-line' }}"></i></button></form>
                @endif
                @if($a->type !== 'stage' && ($a->user_id === auth()->id() || auth()->user()->can('Manage clients')))
                    <form method="POST" action="{{ route('crm.activities.destroy', $a) }}" class="d-inline" onsubmit="return confirm('Remove this entry?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i></button></form>
                @endif
            </div>
        </div>
    @empty
        <div class="text-muted small border-top pt-2">Nothing logged yet.</div>
    @endforelse
</x-cb.card>
