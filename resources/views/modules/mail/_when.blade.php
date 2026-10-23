@php
    /** Gmail-style short date: time today, day and month this year, full date before. */
    $__w = $at ? $at->copy()->timezone(config('app.timezone')) : null;
@endphp
@if($__w)<span title="{{ $__w->format('D, j M Y, g:i A') }}">{{ $__w->isToday() ? $__w->format('g:i A') : ($__w->isCurrentYear() ? $__w->format('j M') : $__w->format('d/m/Y')) }}</span>@endif
