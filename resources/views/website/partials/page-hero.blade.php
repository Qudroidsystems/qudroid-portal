{{-- Inner-page banner: @include('website.partials.page-hero', ['title' => ..., 'lead' => ..., 'crumbs' => [['Label', url], ...], 'image' => url]) --}}
<section class="page-hero {{ !empty($image) ? 'has-img' : '' }}" @if(!empty($image)) style="background-image:url('{{ $image }}')" @endif>
    <div class="container">
        <nav class="crumbs" aria-label="Breadcrumb">
            <a href="{{ \App\Services\Website\WebsiteContent::homeUrl() }}">Home</a>
            @foreach(($crumbs ?? []) as [$label, $link])
                <span>/</span>@if($link)<a href="{{ $link }}">{{ $label }}</a>@else<span style="color:#fff">{{ $label }}</span>@endif
            @endforeach
        </nav>
        <h1 class="reveal">{{ $title }}</h1>
        @if(!empty($lead))<p class="reveal d1">{{ $lead }}</p>@endif
    </div>
</section>
