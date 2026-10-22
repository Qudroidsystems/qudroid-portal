@php
    $tone = match ($kind ?? 'order') {
        'asset' => ['in_store' => 'info', 'assigned' => 'success', 'repair' => 'warning', 'retired' => 'dark', 'lost' => 'danger'][$value] ?? 'secondary',
        'move' => ['receive' => 'success', 'issue' => 'primary', 'adjust' => 'warning', 'transfer' => 'info'][$value] ?? 'secondary',
        default => ['draft' => 'secondary', 'ordered' => 'info', 'partial' => 'warning', 'received' => 'success', 'cancelled' => 'dark'][$value] ?? 'secondary',
    };
    $label = match ($kind ?? 'order') {
        'asset' => \App\Models\Inventory\Asset::STATUSES[$value] ?? ucfirst($value),
        'move' => \App\Models\Inventory\Movement::TYPES[$value] ?? ucfirst($value),
        default => \App\Models\Inventory\PurchaseOrder::STATUSES[$value] ?? ucfirst($value),
    };
@endphp
<span class="badge bg-{{ $tone }}-subtle text-{{ $tone }} status-pill">{{ $label }}</span>
