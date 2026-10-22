{{-- One stock movement as a table row. $mv, $showItem --}}
@php $q = \App\Models\Inventory\Item::qty(abs($mv->quantity)); @endphp
<tr>
    <td data-label="When" class="text-nowrap">{{ $mv->created_at->format('j M Y H:i') }}</td>
    @if($showItem ?? false)<td data-label="Item"><a href="{{ route('inventory.items.show', $mv->item) }}">{{ $mv->item->name }}</a></td>@endif
    <td data-label="What">@include('modules.inventory._pill', ['value' => $mv->type, 'kind' => 'move'])</td>
    <td data-label="Qty" class="num {{ $mv->quantity < 0 ? 'text-danger' : 'text-success' }}">{{ $mv->quantity < 0 ? '−' : '+' }}{{ $q }}</td>
    <td data-label="Location">{{ $mv->location->name }}</td>
    <td data-label="Balance" class="num">{{ \App\Models\Inventory\Item::qty($mv->balance) }}</td>
    <td data-label="Details" class="small">
        @if($mv->recipient)To {{ $mv->recipient->name }} @endif
        @if($mv->project)· <a href="{{ route('projects.show', $mv->project) }}">{{ $mv->project->code }}</a> @endif
        @if($mv->purchaseOrder)<a href="{{ route('inventory.orders.show', $mv->purchaseOrder) }}">{{ $mv->purchaseOrder->number }}</a> @elseif($mv->reference){{ $mv->reference }} @endif
        @if($mv->note)<div class="text-muted">{{ $mv->note }}</div>@endif
    </td>
    <td data-label="By" class="small text-muted">{{ $mv->creator?->name }}</td>
</tr>
