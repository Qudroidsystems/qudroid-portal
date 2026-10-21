@extends('layouts.master')

@php
    $labels = ['contact' => 'Messages', 'admission' => 'Admission applications', 'visit' => 'Visit requests', 'newsletter' => 'Newsletter'];
    $dataLabels = ['child_name' => 'Child', 'date_of_birth' => 'Date of birth', 'gender' => 'Gender', 'class_sought' => 'Class', 'current_school' => 'Current school', 'visit_date' => 'Visit date'];
@endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Website messages" icon="ri-mail-line" subtitle="Admission applications, visit requests, contact messages and newsletter sign-ups from the website." />
    @include('website.admin._nav')

    <x-cb.card :title="$type ? ($labels[$type] ?? ucfirst($type)) : 'All messages'" icon="ri-inbox-line" :count="$rows->total()" :flush="true">
        <x-slot:tools>
            <a class="btn btn-sm {{ !$type ? 'btn-primary' : 'btn-light' }}" href="{{ route('website.admin.messages') }}">All</a>
            @foreach($labels as $k => $l)
                <a class="btn btn-sm {{ $type === $k ? 'btn-primary' : 'btn-light' }}" href="{{ route('website.admin.messages', ['type' => $k]) }}">{{ $l }}
                    @if(($counts[$k]->unread ?? 0) && $k !== 'newsletter')<span class="badge bg-danger ms-1">{{ $counts[$k]->unread }}</span>@endif</a>
            @endforeach
            <a class="btn btn-sm btn-outline-success" href="{{ route('website.admin.messages.export', ['type' => $type ?: 'admission']) }}"><i class="ri-file-excel-2-line me-1"></i>CSV</a>
        </x-slot:tools>
        <div class="list-group list-group-flush">
            @forelse($rows as $m)
                <div class="list-group-item py-3 {{ $m->read_at ? '' : 'bg-primary-subtle bg-opacity-25' }}" data-id="{{ $m->id }}">
                    <div class="d-flex gap-3">
                        <span class="badge {{ ['admission' => 'bg-success', 'visit' => 'bg-info', 'newsletter' => 'bg-secondary'][$m->type] ?? 'bg-primary' }} align-self-start text-capitalize">{{ $m->type }}</span>
                        <div class="flex-grow-1">
                            <div class="d-flex justify-content-between gap-2 flex-wrap">
                                <a class="fw-semibold text-reset msg-toggle" href="#m{{ $m->id }}" data-bs-toggle="collapse">{{ $m->subject ?: ($m->name ?: $m->email) }}</a>
                                <span class="small text-muted">{{ $m->created_at->format('d M Y, H:i') }}</span>
                            </div>
                            <div class="small text-muted">{{ $m->name }}
                                @if($m->phone) · <a href="tel:{{ $m->phone }}">{{ $m->phone }}</a> · <a href="https://wa.me/{{ preg_replace('/^0/', '234', preg_replace('/\D/', '', $m->phone)) }}" target="_blank">WhatsApp</a>@endif
                                @if($m->email) · <a href="mailto:{{ $m->email }}">{{ $m->email }}</a>@endif
                            </div>
                            <div class="collapse mt-2" id="m{{ $m->id }}">
                                @if($m->data)<div class="d-flex flex-wrap gap-3 small mb-2">@foreach($m->data as $k => $val)@if($val)<span><b>{{ $dataLabels[$k] ?? $k }}:</b> {{ $val }}</span>@endif @endforeach</div>@endif
                                @if($m->message)<div style="white-space:pre-line">{{ $m->message }}</div>@endif
                                <form method="POST" action="{{ route('website.admin.messages.delete', $m->id) }}" class="mt-2" onsubmit="return confirm('Delete this message?')">@csrf @method('DELETE')<button class="btn btn-sm btn-outline-danger">Delete</button></form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="text-center text-muted py-5">No messages here yet.</div>
            @endforelse
        </div>
        @if($rows->hasPages())<div class="p-3">{{ $rows->links() }}</div>@endif
    </x-cb.card>
</div></div></div>
<script>
document.querySelectorAll('.msg-toggle').forEach(a => a.addEventListener('click', () => {
    const row = a.closest('[data-id]');
    if (!row.classList.contains('bg-primary-subtle')) return;
    fetch('{{ url('admin/website/messages') }}/' + row.dataset.id + '/read', { method: 'POST', headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', Accept: 'application/json' } });
    row.classList.remove('bg-primary-subtle', 'bg-opacity-25');
}));
</script>
@endsection
