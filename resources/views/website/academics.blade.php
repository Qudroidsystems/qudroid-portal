@extends('website.layout', ['pageTitle' => 'Academics'])

@section('content')
    @include('website.partials.page-hero', ['title' => 'Academics', 'lead' => 'A broad, balanced curriculum that builds strong foundations and prepares learners for national and international examinations.', 'crumbs' => [['Academics', null]]])
    <section class="section">
        <div class="container">
            @if($pages->count())
                <div class="grid-3" style="margin-bottom:70px">
                    @foreach($pages as $i => $p)
                        <a class="value reveal d{{ $i % 3 + 1 }}" href="{{ route('site.page', $p->slug) }}" data-n="{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}" style="color:inherit">
                            <i class="ri-book-2-line"></i><h3>{{ $p->title }}</h3><p>{{ $p->summary }}</p>
                        </a>
                    @endforeach
                </div>
            @endif

            <div class="split" style="align-items:start">
                <div class="reveal">
                    <span class="eyebrow">Classes</span>
                    <h2>Our classes</h2>
                    @forelse($academics['levels'] as $level => $classes)
                        <h3 style="margin-top:22px">{{ $level }}</h3>
                        <div class="chips" style="justify-content:flex-start;margin-bottom:0">@foreach($classes as $c)<span class="chip">{{ $c }}</span>@endforeach</div>
                    @empty
                        <p class="muted">Class information will appear here.</p>
                    @endforelse
                </div>
                <div class="reveal d2">
                    <span class="eyebrow">Subjects</span>
                    <h2>What we teach</h2>
                    @if($academics['subjects']->count())
                        <ul class="checks" style="grid-template-columns:1fr 1fr">@foreach($academics['subjects'] as $sub)<li>{{ $sub }}</li>@endforeach</ul>
                    @else
                        <p class="muted">Subject information will appear here.</p>
                    @endif
                </div>
            </div>
        </div>
    </section>
    <section class="section soft">
        <div class="container">@include('website.partials.app-promo')</div>
    </section>
@endsection
