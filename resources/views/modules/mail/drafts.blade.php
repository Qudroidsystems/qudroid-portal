@extends('modules.mail.layout')

@section('mail')
<div class="qm-bar">
    <span class="small text-muted ms-2">
        @if($view === 'scheduled') Messages here go out at the time shown. Open one to change it (that cancels the schedule until you send again).
        @else Drafts save on their own as you write. @endif
    </span>
</div>
@if($drafts->isEmpty())
    <div class="qm-empty"><i class="{{ $view === 'scheduled' ? 'ri-calendar-schedule-line' : 'ri-draft-line' }}"></i>{{ $view === 'scheduled' ? 'No scheduled messages.' : 'You don\'t have any saved drafts.' }}</div>
@else
    <ul class="qm-list">
        @foreach($drafts as $d)
            <li class="qm-row" data-compose="new" data-draft="{{ $d->id }}" style="grid-template-columns: minmax(120px, 220px) 1fr auto auto">
                <div class="who"><span class="text-danger">{{ $view === 'scheduled' ? 'Scheduled' : 'Draft' }}</span> {{ $d->to ? mb_strimwidth($d->to, 0, 40, '…') : '(no recipients)' }}</div>
                <div class="what">{{ $d->subject ?: '(no subject)' }} <span class="snip">— {{ mb_strimwidth(trim(preg_replace('/\s+/', ' ', html_entity_decode(strip_tags(str_replace(['<br>', '</p>'], ' ', (string) $d->body_html))))), 0, 120, '…') }}</span></div>
                <div class="when">
                    @if($d->status === 'scheduled')<span class="text-warning">{{ $d->send_at?->format('D j M, g:i A') }}</span>
                    @if($d->error)<i class="ri-error-warning-line text-danger ms-1" title="{{ $d->error }}"></i>@endif
                    @else @include('modules.mail._when', ['at' => $d->updated_at]) @endif
                </div>
                <form method="POST" action="{{ route('mail.drafts.discard', [$account, $d]) }}" onclick="event.stopPropagation()" onsubmit="return confirm('Discard this {{ $view === 'scheduled' ? 'scheduled message' : 'draft' }}?')">
                    @csrf @method('DELETE')
                    <button class="btn btn-sm btn-link text-muted" aria-label="Discard"><i class="ri-delete-bin-line"></i></button>
                </form>
            </li>
        @endforeach
    </ul>
    <div class="p-2">{{ $drafts->links() }}</div>
@endif
@push('scripts')
<script>document.addEventListener('qm:sent', () => setTimeout(() => location.reload(), 400));</script>
@endpush
@endsection
