@php
    $tone = ['paid' => 'success', 'confirmed' => 'success', 'partially_paid' => 'info', 'issued' => 'warning', 'pending' => 'warning', 'submitted' => 'warning', 'approved' => 'info', 'rejected' => 'danger',
             'overdue' => 'danger', 'failed' => 'danger', 'void' => 'dark', 'reversed' => 'dark'][$status] ?? 'secondary';
@endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ $label }}</span>
