{{-- A phone frame around one app screenshot. $src (url), $alt, $class (optional). --}}
<div class="device {{ $class ?? '' }}" @isset($style) style="{{ $style }}" @endisset>
    <span class="device-btn l1"></span><span class="device-btn l2"></span><span class="device-btn r1"></span>
    <div class="device-screen"><img src="{{ $src }}" alt="{{ $alt ?? 'App screen' }}" loading="lazy"></div>
</div>
