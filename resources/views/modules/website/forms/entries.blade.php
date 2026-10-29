@extends('layouts.master')

@section('content')
@php $fields = collect($form->fields ?? []); @endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$form->name . ': entries'" icon="ri-inbox-line" subtitle="What people sent from the website." :back="route('website.forms.index')" back-label="Forms">
        <x-slot:actions>
            <a class="cb-hero-btn" href="{{ route('website.forms.export', $form) }}"><i class="ri-download-2-line me-1"></i>Download CSV</a>
            <a class="cb-hero-btn" href="{{ route('website.forms.edit', $form) }}"><i class="ri-edit-line me-1"></i>Edit form</a>
        </x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <ul class="nav nav-pills mb-3 gap-1">
        @foreach([null => 'Inbox', 'new' => 'New', 'read' => 'Read', 'archived' => 'Archived'] as $k => $label)
            <li class="nav-item"><a class="nav-link {{ $status === ($k ?: null) ? 'active' : '' }}" href="{{ route('website.forms.entries', [$form] + ($k ? ['status' => $k] : [])) }}">{{ $label }}</a></li>
        @endforeach
    </ul>

    @forelse($entries as $e)
        <div class="card mb-2 {{ $e->status === 'new' ? 'border-primary' : '' }}">
            <div class="card-body">
                <div class="d-flex justify-content-between gap-2 flex-wrap mb-2">
                    <div>
                        <span class="fw-semibold">{{ $e->name ?: 'Someone' }}</span>
                        @if($e->email)<a href="mailto:{{ $e->email }}" class="ms-2 small">{{ $e->email }}</a>@endif
                        @if($e->phone)<a href="tel:{{ $e->phone }}" class="ms-2 small">{{ $e->phone }}</a>@endif
                        @if($e->status === 'new')<span class="badge bg-primary ms-1">New</span>@endif
                    </div>
                    <small class="text-muted">{{ $e->created_at->format('j M Y, H:i') }} · {{ $e->created_at->diffForHumans() }}</small>
                </div>
                <dl class="row small mb-2">
                    @foreach($fields as $f)
                        @php $v = $e->data[$f['key']] ?? null; @endphp
                        @continue($v === null || $v === '' || in_array($f['key'], ['name', 'email', 'phone'], true))
                        <dt class="col-sm-3 text-muted fw-normal">{{ $f['label'] }}</dt>
                        <dd class="col-sm-9" style="white-space:pre-line">{{ is_bool($v) ? ($v ? 'Yes' : 'No') : $v }}</dd>
                    @endforeach
                </dl>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    @if($e->deal)
                        @can('View clients')<a href="{{ route('crm.deals.show', $e->deal) }}" class="btn btn-sm btn-soft-success"><i class="ri-briefcase-4-line me-1"></i>Lead in CRM</a>@else<span class="badge bg-success-subtle text-success">In CRM</span>@endcan
                    @endif
                    @foreach(['read' => ['Mark read', 'ri-mail-open-line'], 'new' => ['Mark new', 'ri-mail-unread-line'], 'archived' => ['Archive', 'ri-archive-line']] as $s => [$label, $icon])
                        @continue($e->status === $s)
                        <form method="POST" action="{{ route('website.entries.status', $e) }}" class="m-0">@csrf<input type="hidden" name="status" value="{{ $s }}"><button class="btn btn-sm btn-soft-secondary"><i class="{{ $icon }} me-1"></i>{{ $label }}</button></form>
                    @endforeach
                    <form method="POST" action="{{ route('website.entries.destroy', $e) }}" class="m-0 ms-auto" onsubmit="return confirm('Delete this entry?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger"><i class="ri-delete-bin-line"></i></button></form>
                    @if($e->page)<small class="text-muted w-100">From {{ $e->page }}</small>@endif
                </div>
            </div>
        </div>
    @empty
        <div class="card"><div class="card-body text-center text-muted p-5"><i class="ri-inbox-line fs-1 d-block mb-2"></i>No entries here.</div></div>
    @endforelse
    {{ $entries->links() }}
</div></div></div>
@endsection
