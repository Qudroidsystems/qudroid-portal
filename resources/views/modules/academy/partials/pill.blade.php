{{-- @include('modules.academy.partials.pill', ['status' => 'active', 'label' => 'Active']) --}}
@php
    $tone = [
        'active' => 'success', 'published' => 'success', 'open' => 'success', 'admitted' => 'info', 'running' => 'info',
        'pending' => 'warning', 'applicant' => 'warning', 'planned' => 'secondary', 'draft' => 'secondary',
        'completed' => 'primary', 'suspended' => 'danger', 'withdrawn' => 'dark', 'cancelled' => 'dark', 'archived' => 'dark',
    ][$status] ?? 'secondary';
@endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ $label ?? ucfirst($status) }}</span>
