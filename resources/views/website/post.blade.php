@extends('website.layout', ['pageTitle' => $post->title, 'metaDescription' => $post->excerpt, 'ogImage' => \App\Services\Website\WebsiteContent::fileUrl($post->cover)])

@php $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p); @endphp

@section('content')
    @include('website.partials.page-hero', [
        'title' => $post->title, 'lead' => $post->excerpt, 'image' => $F($post->cover),
        'crumbs' => [[$post->type === 'event' ? 'Events' : 'News', route('site.news', ['type' => $post->type])], [\Illuminate\Support\Str::limit($post->title, 40), null]],
    ])
    <section class="section">
        <div class="container with-side">
            <article>
                <div class="card" style="margin-bottom:30px;flex-direction:row;flex-wrap:wrap;gap:24px;padding:18px 24px;align-items:center">
                    @if($post->type === 'event' && $post->event_date)
                        <span><i class="ri-calendar-event-line" style="color:var(--primary)"></i> <b style="color:var(--ink)">{{ $post->event_date->format('l, d F Y') }}</b></span>
                        @if($post->event_time)<span><i class="ri-time-line" style="color:var(--primary)"></i> {{ $post->event_time }}</span>@endif
                        @if($post->location)<span><i class="ri-map-pin-line" style="color:var(--primary)"></i> {{ $post->location }}</span>@endif
                    @else
                        <span><i class="ri-calendar-line" style="color:var(--primary)"></i> {{ optional($post->published_at ?? $post->created_at)->format('d F Y') }}</span>
                        @if($post->category)<span><i class="ri-price-tag-3-line" style="color:var(--primary)"></i> {{ $post->category }}</span>@endif
                    @endif
                </div>
                @if($post->cover)<img src="{{ $F($post->cover) }}" alt="{{ $post->title }}" style="border-radius:22px;width:100%;margin-bottom:30px" class="reveal">@endif
                <div class="prose reveal">{!! $post->body !!}</div>
                <div style="margin-top:36px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
                    <b style="color:var(--ink);font-family:var(--head)">Share:</b>
                    <a class="chip" target="_blank" rel="noopener" href="https://wa.me/?text={{ urlencode($post->title . ' ' . url()->current()) }}"><i class="ri-whatsapp-line"></i> WhatsApp</a>
                    <a class="chip" target="_blank" rel="noopener" href="https://www.facebook.com/sharer/sharer.php?u={{ urlencode(url()->current()) }}"><i class="ri-facebook-fill"></i> Facebook</a>
                    <a class="chip" target="_blank" rel="noopener" href="https://twitter.com/intent/tweet?url={{ urlencode(url()->current()) }}&text={{ urlencode($post->title) }}"><i class="ri-twitter-x-line"></i> X</a>
                </div>
            </article>
            <aside class="side">
                @if($more->count())
                    <div class="box">
                        <h4>More {{ $post->type === 'event' ? 'events' : 'news' }}</h4>
                        @foreach($more as $m)<a class="sib" href="{{ route('site.post', $m->slug) }}">{{ \Illuminate\Support\Str::limit($m->title, 48) }} <i class="ri-arrow-right-s-line"></i></a>@endforeach
                    </div>
                @endif
                @include('website.partials.contact-box')
            </aside>
        </div>
    </section>
@endsection
