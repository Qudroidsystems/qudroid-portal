@php
    $tone = ['active' => 'success', 'suspended' => 'warning', 'exited' => 'dark',
             'pending' => 'warning', 'approved' => 'success', 'rejected' => 'danger', 'cancelled' => 'secondary',
             'draft' => 'secondary', 'paid' => 'success'][$value] ?? 'secondary';
    if (($kind ?? null) === 'run') $tone = ['draft' => 'secondary', 'approved' => 'info', 'paid' => 'success'][$value] ?? 'secondary';
    $label = match ($kind ?? null) {
        'employee' => \App\Models\Hr\Employee::STATUSES[$value] ?? ucfirst($value),
        'run' => \App\Models\Hr\PayrollRun::STATUSES[$value] ?? ucfirst($value),
        default => \App\Models\Hr\LeaveRequest::STATUSES[$value] ?? ucfirst($value),
    };
@endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ $label }}</span>
