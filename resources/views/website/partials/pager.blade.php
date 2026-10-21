@if($pg->hasPages())
    <nav class="pager" aria-label="Pages">
        @if($pg->onFirstPage())<span class="off"><i class="ri-arrow-left-s-line"></i></span>@else<a href="{{ $pg->previousPageUrl() }}" aria-label="Previous"><i class="ri-arrow-left-s-line"></i></a>@endif
        @foreach(range(1, $pg->lastPage()) as $n)
            @if($n === $pg->currentPage())<span class="on">{{ $n }}</span>@elseif(abs($n - $pg->currentPage()) < 3 || $n === 1 || $n === $pg->lastPage())<a href="{{ $pg->url($n) }}">{{ $n }}</a>@endif
        @endforeach
        @if($pg->hasMorePages())<a href="{{ $pg->nextPageUrl() }}" aria-label="Next"><i class="ri-arrow-right-s-line"></i></a>@else<span class="off"><i class="ri-arrow-right-s-line"></i></span>@endif
    </nav>
@endif
