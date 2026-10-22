@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="Follow-ups" icon="ri-alarm-line" subtitle="Calls to make and emails to send, soonest first." :back="route('crm.index')" back-label="CRM" />

    @include('partials.flash')

    <x-cb.card :title="$mine ? 'Mine' : 'Everyone\'s'" icon="ri-alarm-line" :count="$items->total()" :flush="true">
        <x-slot:tools>
            <a href="{{ route('crm.follow-ups', ['who' => 'me']) }}" class="btn btn-sm {{ $mine ? 'btn-primary' : 'btn-soft-secondary' }}">Mine</a>
            <a href="{{ route('crm.follow-ups', ['who' => 'all']) }}" class="btn btn-sm {{ $mine ? 'btn-soft-secondary' : 'btn-primary' }}">Everyone's</a>
        </x-slot:tools>
        @if($items->isEmpty())
            <div class="empty-state"><i class="ri-alarm-line"></i><h6>Nothing to follow up</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th></th><th>What</th><th>About</th>@unless($mine)<th>For</th>@endunless<th>Due</th></tr></thead>
                <tbody>
                @foreach($items as $f)
                    <tr>
                        <td><form method="POST" action="{{ route('crm.activities.complete', $f) }}">@csrf<button class="btn btn-sm btn-soft-success" title="Done"><i class="ri-check-line"></i></button></form></td>
                        <td data-label="What">{{ $f->body }}</td>
                        <td data-label="About"><a href="{{ $crm->link($f) }}">{{ $f->subject?->title ?? $f->subject?->name }}</a>@if($f->company && !($f->subject instanceof \App\Models\Crm\Company))<div class="small text-muted">{{ $f->company->name }}</div>@endif</td>
                        @unless($mine)<td data-label="For">{{ $f->assignee?->name ?? '—' }}</td>@endunless
                        <td data-label="Due" class="text-nowrap {{ $f->isOverdue() ? 'text-danger fw-semibold' : '' }}">{{ $f->due_at->format('D j M, g:ia') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
            <div class="p-3">{{ $items->links() }}</div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
