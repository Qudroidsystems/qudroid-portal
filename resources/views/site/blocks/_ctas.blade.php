{{-- Up to two calls to action: the blue pill, then a chevron link. --}}
@if(($primary['label'] ?? '') && ($primary['url'] ?? '') || ($secondary['label'] ?? '') && ($secondary['url'] ?? ''))
    <div class="s-ctas">
        @if(($primary['label'] ?? '') && ($primary['url'] ?? ''))<a class="s-btn" href="{{ $primary['url'] }}">{{ $primary['label'] }}</a>@endif
        @if(($secondary['label'] ?? '') && ($secondary['url'] ?? ''))<a class="s-more" href="{{ $secondary['url'] }}">{{ $secondary['label'] }}</a>@endif
    </div>
@endif
