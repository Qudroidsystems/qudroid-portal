@php
    $tones = [
        'status'   => ['planning' => 'secondary', 'active' => 'success', 'on_hold' => 'warning', 'completed' => 'primary', 'cancelled' => 'dark',
                       'todo' => 'secondary', 'in_progress' => 'info', 'review' => 'warning', 'done' => 'success'],
        'priority' => ['low' => 'secondary', 'normal' => 'info', 'high' => 'warning', 'urgent' => 'danger'],
    ];
    $tone = $tones[$kind ?? 'status'][$value] ?? 'secondary';
    $label = ($kind ?? 'status') === 'priority'
        ? (\App\Models\Projects\Project::PRIORITIES[$value] ?? $value)
        : (\App\Models\Projects\Project::STATUSES[$value] ?? \App\Models\Projects\Task::STATUSES[$value] ?? $value);
@endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ $label }}</span>
