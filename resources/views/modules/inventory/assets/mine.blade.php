@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <x-cb.hero title="My equipment" icon="ri-macbook-line" subtitle="Company equipment assigned to you. Tell the store if something is wrong with it." />

    @include('partials.flash')

    <x-cb.card title="Assigned to me" icon="ri-macbook-line" :count="$assets->count()" :flush="true">
        @if($assets->isEmpty())
            <div class="empty-state"><i class="ri-macbook-line"></i><h6>Nothing is assigned to you</h6></div>
        @else
            <div class="table-responsive"><table class="cb-table stack">
                <thead><tr><th>Item</th><th>Tag</th><th>Serial</th><th>Since</th></tr></thead>
                <tbody>
                @foreach($assets as $a)
                    <tr>
                        <td data-label="Item" class="fw-semibold">{{ $a->name }}@if($a->make_model)<div class="small text-muted fw-normal">{{ $a->make_model }}</div>@endif</td>
                        <td data-label="Tag">{{ $a->tag }}</td>
                        <td data-label="Serial">{{ $a->serial_number ?? '—' }}</td>
                        <td data-label="Since">{{ $a->assigned_at?->format('j M Y') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table></div>
        @endif
    </x-cb.card>

</div></div></div>
@endsection
