{{-- Draws a page's blocks. Each block type has a partial named after it. --}}
@foreach($blocks as $b)
    @if(is_array($b) && view()->exists('site.blocks.' . ($b['type'] ?? '')))
        @include('site.blocks.' . $b['type'], ['b' => $b, 'first' => $loop->first])
    @endif
@endforeach
