@php
    $tone = ['prospect' => 'info', 'client' => 'success', 'inactive' => 'secondary',
             'new' => 'secondary', 'contacted' => 'info', 'qualified' => 'primary', 'proposal' => 'warning', 'negotiation' => 'warning',
             'won' => 'success', 'lost' => 'dark'][$value] ?? 'secondary';
    $label = \App\Models\Crm\Company::STATUSES[$value] ?? (\App\Models\Crm\Deal::STAGES[$value][0] ?? ucfirst($value));
@endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ $label }}</span>
