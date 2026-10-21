@extends('website.layout', ['pageTitle' => $album->title, 'metaDescription' => $album->description])

@php $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p); @endphp

@section('content')
    @include('website.partials.page-hero', [
        'title' => $album->title,
        'lead' => trim(($album->taken_on ? $album->taken_on->format('d F Y') . ' · ' : '') . $album->photos->count() . ' photos' . ($album->description ? ' — ' . $album->description : '')),
        'crumbs' => [['Gallery', route('site.gallery')], [\Illuminate\Support\Str::limit($album->title, 40), null]],
        'image' => ($c = $album->coverPhoto()) ? $F($c->path) : null,
    ])
    <section class="section">
        <div class="container">
            <div class="masonry">
                @foreach($album->photos as $p)
                    <a href="{{ $F($p->path) }}" data-lightbox="album" data-caption="{{ $p->caption }}" class="reveal">
                        <img src="{{ $F($p->thumb ?: $p->path) }}" alt="{{ $p->caption ?: $album->title }}" loading="lazy" @if($p->width) width="{{ $p->width }}" height="{{ $p->height }}" @endif>
                    </a>
                @endforeach
            </div>
            <div class="center" style="margin-top:30px"><a class="btn btn-outline" href="{{ route('site.gallery') }}"><i class="ri-arrow-left-line"></i> All albums</a></div>
        </div>
    </section>
@endsection
