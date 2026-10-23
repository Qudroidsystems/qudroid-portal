{{-- label, value, sub (optional), now/before (optional numbers for the change), goodWhenUp (default true), icon --}}
@php
    $chg = isset($before) ? \App\Services\Reports\CommandCenter::change($now, $before) : null;
    $good = $chg === null || $chg == 0 ? null : (($chg > 0) === ($goodWhenUp ?? true));
@endphp
<div class="cc-tile">
    <div class="d-flex justify-content-between align-items-start gap-2">
        <span class="cc-label">{{ $label }}</span>
        <i class="{{ $icon }} cc-icon"></i>
    </div>
    <div class="cc-value">{{ $value }}</div>
    <div class="cc-sub">
        @if($chg !== null)
            <span class="cc-delta {{ $good === null ? '' : ($good ? 'up' : 'down') }}"><i class="{{ $chg >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i>{{ abs($chg) }}%</span>
            <span>vs {{ $vs }}</span>
        @elseif(isset($before))
            <span>nothing in {{ $vs }} to compare</span>
        @endif
        @isset($sub)<div>{{ $sub }}</div>@endisset
    </div>
</div>
