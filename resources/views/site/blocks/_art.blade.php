{{-- A picture, or generated art (an icon in a soft glow) when there isn't one. --}}
@if(!empty($image))
    <img src="{{ $image }}" alt="{{ $alt ?? '' }}" loading="lazy">
@else
    <div class="s-art {{ $class ?? '' }}" style="--art: {{ $color ?: '#0EA5E9' }}"><i class="{{ $icon ?: 'ri-magic-line' }}"></i></div>
@endif
