{{--
    The Qlouds logotype (same design and animation as the mobile app). "Q" in sky blue, the rest of the first word
    after it, and any further words dimmed: "Qlouds Workspace".
      <x-qlouds-logo />                               static, name from config('qudroid.name')
      <x-qlouds-logo animate size="56px" :rest="false" />   the entrance animation, first word only
      <x-qlouds-logo dark />                          for dark backgrounds
      <x-qlouds-logo mark />                          just the Q
--}}
@props(['animate' => false, 'dark' => false, 'mark' => false, 'rest' => true, 'size' => null, 'name' => null])
@php
    $qlName = trim($name ?? ($brand['name'] ?? config('qudroid.name', 'Qlouds Workspace')));
    $qlWords = preg_split('/\s+/', $qlName, 2);
    $qlFirst = $qlWords[0] ?? 'Qlouds';
    $qlLead = mb_substr($qlFirst, 0, 1);
    $qlTail = mb_substr($qlFirst, 1);
    $qlRest = $rest && !$mark ? ($qlWords[1] ?? '') : '';
@endphp
@once
    <link rel="stylesheet" href="{{ asset('css/qlouds-logo.css') }}?v={{ @filemtime(public_path('css/qlouds-logo.css')) }}">
@endonce
<span {{ $attributes->class(['qlogo', 'qlogo--animate' => $animate, 'qlogo--dark' => $dark]) }} @if($size) style="font-size: {{ $size }}" @endif
      role="img" aria-label="{{ $mark ? $qlFirst : ($qlRest ? $qlFirst . ' ' . $qlRest : $qlFirst) }}">
    <span class="qlogo-q" aria-hidden="true">@if($animate)<span class="qlogo-ghost a">{{ $qlLead }}</span><span class="qlogo-ghost b">{{ $qlLead }}</span>@endif<span class="qlogo-core">{{ $qlLead }}</span></span>@unless($mark)@if($animate)@foreach(mb_str_split($qlTail) as $i => $ch)<span class="qlogo-l" style="--i:{{ $i }}" aria-hidden="true">{{ $ch }}</span>@endforeach @else<span aria-hidden="true">{{ $qlTail }}</span>@endif @if($qlRest)<span class="qlogo-rest" aria-hidden="true"> {{ $qlRest }}</span>@endif @endunless
</span>
