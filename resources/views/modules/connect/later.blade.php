@extends('layouts.master')

@php
    $fmt = fn ($t) => $t->isToday() ? 'Today ' . $t->format('g:i a') : ($t->isTomorrow() ? 'Tomorrow ' . $t->format('g:i a') : $t->format('D j M, g:i a'));
    $openReminders = $reminders->whereNull('done_at');
    $pending = $scheduled->whereNull('sent_at');
@endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Later" icon="ri-alarm-line" subtitle="Your reminders and the messages you scheduled to send." :back="route('connect.index')" back-label="Connect" />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-6">
            <x-cb.card title="Reminders" icon="ri-alarm-line" :count="$openReminders->count()">
                <form method="POST" action="{{ route('connect.reminders.store') }}" class="d-flex flex-wrap gap-2 mb-3" data-live>
                    @csrf
                    <input name="note" class="form-control form-control-sm flex-grow-1" style="min-width: 12rem" maxlength="255" required placeholder="Remind me to…" aria-label="What to be reminded about">
                    <select name="when" class="form-select form-select-sm" style="max-width: 12rem" aria-label="When">
                        @foreach($choices as $k => $v)<option value="{{ $k }}" @selected($k === '60')>{{ $v }}</option>@endforeach
                        <option value="">At a time I pick…</option>
                    </select>
                    <input type="datetime-local" name="at" class="form-control form-control-sm" style="max-width: 13rem" aria-label="Pick a time" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}">
                    <button class="btn btn-sm btn-primary">Add</button>
                </form>

                @forelse($reminders as $r)
                    @php $due = !$r->done_at && $r->remind_at->isPast(); @endphp
                    <div class="lt-row {{ $r->done_at ? 'done' : '' }} {{ $due ? 'due' : '' }}">
                        <form method="POST" action="{{ route('connect.reminders.done', $r) }}" data-live>@csrf
                            @if($r->done_at)<input type="hidden" name="undo" value="1">@endif
                            <button class="btn btn-sm p-0 border-0 bg-transparent fs-5 {{ $r->done_at ? 'text-success' : 'text-muted' }}" title="{{ $r->done_at ? 'Reopen' : 'Mark done' }}" aria-label="{{ $r->done_at ? 'Reopen' : 'Mark done' }}"><i class="{{ $r->done_at ? 'ri-checkbox-circle-fill' : 'ri-checkbox-blank-circle-line' }}"></i></button>
                        </form>
                        <div class="flex-grow-1 min-w-0">
                            @if($r->note)<div class="fw-semibold">{{ $r->note }}</div>@endif
                            @if($r->message)
                                <a href="{{ \App\Services\Connect\LaterService::reminderUrl($r) }}" class="small d-block text-truncate"><strong>{{ $r->message->user?->name ?? 'Someone' }}</strong> in {{ $r->message->conversation?->titleFor($me) }}: {{ $r->message->preview(90) }}</a>
                            @endif
                            <div class="small {{ $due ? 'text-danger fw-semibold' : 'text-muted' }}"><i class="ri-time-line"></i> {{ $fmt($r->remind_at) }}@if($r->done_at) · done @endif</div>
                        </div>
                        @unless($r->done_at)
                            <form method="POST" action="{{ route('connect.reminders.snooze', $r) }}" data-live data-live-auto>@csrf
                                <select name="when" class="form-select form-select-sm lt-snooze" aria-label="Snooze"><option value="">Snooze…</option>@foreach($choices as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach</select>
                            </form>
                        @endunless
                        <form method="POST" action="{{ route('connect.reminders.destroy', $r) }}" data-live>@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" aria-label="Delete reminder"><i class="ri-delete-bin-line"></i></button></form>
                    </div>
                @empty
                    <div class="text-muted small">No reminders. Use "Remind me" on any message, or add one above.</div>
                @endforelse
            </x-cb.card>
        </div>

        <div class="col-xl-6">
            <x-cb.card title="Scheduled messages" icon="ri-send-plane-line" :count="$pending->count()">
                @forelse($scheduled as $s)
                    <div class="lt-row {{ $s->sent_at ? 'done' : '' }}">
                        <i class="fs-5 {{ $s->sent_at ? 'ri-check-double-line text-success' : ($s->failed_reason ? 'ri-error-warning-line text-danger' : 'ri-time-line text-muted') }}"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="small text-muted">To <a href="{{ route('connect.show', $s->conversation_id) }}">{{ $s->conversation?->titleFor($me) ?? 'a removed conversation' }}</a>
                                · {{ $s->sent_at ? 'sent ' . $fmt($s->sent_at) : $fmt($s->send_at) }}</div>
                            <div class="lt-body">{{ $s->body }}</div>
                            @if($s->failed_reason)<div class="small text-danger">Not sent: {{ $s->failed_reason }}</div>@endif
                        </div>
                        @unless($s->sent_at)
                            <div class="d-flex flex-column gap-1 align-items-end">
                                <form method="POST" action="{{ route('connect.scheduled.update', $s) }}" data-live data-live-auto>@csrf @method('PUT')
                                    <input type="datetime-local" name="at" class="form-control form-control-sm" value="{{ $s->send_at->format('Y-m-d\TH:i') }}" min="{{ now()->addMinute()->format('Y-m-d\TH:i') }}" aria-label="Send at">
                                </form>
                                <div class="d-flex gap-1">
                                    <form method="POST" action="{{ route('connect.scheduled.send', $s) }}" data-live>@csrf<button class="btn btn-sm btn-soft-primary">Send now</button></form>
                                    <form method="POST" action="{{ route('connect.scheduled.cancel', $s) }}" data-live data-confirm="Cancel this scheduled message?">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" aria-label="Cancel"><i class="ri-close-line"></i></button></form>
                                </div>
                            </div>
                        @endunless
                    </div>
                @empty
                    <div class="text-muted small">Nothing scheduled. In a chat, use the clock button next to Send to send a message later.</div>
                @endforelse
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection

@push('styles')
<style>
    .lt-row { display: flex; gap: 10px; align-items: flex-start; padding: 10px 0; border-top: 1px solid var(--cb-border); }
    .lt-row.done { opacity: .6; }
    .lt-row.due { background: rgba(244, 63, 94, .05); }
    .lt-body { white-space: pre-line; overflow-wrap: anywhere; }
    .lt-snooze { width: auto; }
</style>
@endpush
