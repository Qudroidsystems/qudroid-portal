@extends('website.layout')

@php
    $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d;
    $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p);
    $heroImage = $F($s('hero_image')) ?: ($albums->first()?->coverPhoto() ? $F($albums->first()->coverPhoto()->path) : null);
    $heroSlides = $slides->count() ? $slides : collect([(object) [
        'title' => $s('hero_title', 'Welcome to ' . $school->name), 'subtitle' => $s('hero_subtitle', $school->motto),
        'image' => $s('hero_image'), 'button_text' => $s('hero_button_text', 'Apply now'), 'button_link' => $s('hero_button_link', route('site.admissions') . '#apply'),
    ]]);
    $photos = $albums->flatMap(fn ($a) => $a->photos->take(3)->map(fn ($p) => ['p' => $p, 'a' => $a]))->take(8);
@endphp

@section('content')
    {{-- Hero --}}
    <section class="hero" aria-label="Welcome">
        @foreach($heroSlides as $i => $sl)
            @php $img = $F($sl->image) ?: $heroImage; @endphp
            <div class="slide {{ $i === 0 ? 'on' : '' }}">
                <div class="bg" @if($img) style="background-image:url('{{ $img }}')" @endif></div>
                <div class="container">
                    <div class="content">
                        <span class="eyebrow" style="color:#fff">{{ $school->name }}</span>
                        <h1>{!! nl2br(e($sl->title)) !!}</h1>
                        @if($sl->subtitle)<p>{{ $sl->subtitle }}</p>@endif
                        <div class="actions">
                            @if($sl->button_text)<a class="btn btn-accent" href="{{ $sl->button_link ?: route('site.admissions') }}">{{ $sl->button_text }} <i class="ri-arrow-right-line"></i></a>@endif
                            <a class="btn btn-ghost" href="{{ isset($menu['about'][0]) ? route('site.page', $menu['about'][0]['slug']) : route('site.people') }}">Discover our school</a>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
        @if($heroSlides->count() > 1)
            <div class="dots">@foreach($heroSlides as $i => $sl)<button class="{{ $i === 0 ? 'on' : '' }}" aria-label="Slide {{ $i + 1 }}"></button>@endforeach</div>
        @endif
    </section>

    {{-- Quick cards --}}
    <div class="quick-cards">
        <div class="container">
            <div class="grid">
                <a href="{{ route('site.academics') }}" class="reveal">
                    <i class="ri-book-open-line"></i>
                    <div><h3>{{ $s('quick1_title', 'Academics') }}</h3><p>{{ $s('quick1_text', 'Classes, subjects and how our learners grow.') }}</p></div>
                </a>
                <a href="{{ route('site.admissions') }}" class="reveal d1">
                    <i class="ri-user-add-line"></i>
                    <div><h3>{{ $s('admission_open', '1') === '1' ? $s('quick2_title', 'Admission ongoing') : 'Admissions' }}</h3><p>{{ $s('quick2_text', "Start your child's application online today.") }}</p></div>
                </a>
                <a href="{{ route('site.app') }}" class="reveal d2">
                    <i class="ri-smartphone-line"></i>
                    <div><h3>{{ $s('quick3_title', 'Our mobile app') }}</h3><p>{{ $s('quick3_text', 'Results, fees, attendance and news on your phone.') }}</p></div>
                </a>
            </div>
        </div>
    </div>

    {{-- Intro --}}
    <section class="section">
        <div class="container split">
            <div class="reveal">
                <span class="eyebrow">Welcome</span>
                <h2>{{ $s('intro_title', 'A school where every child is known') }}</h2>
                <div class="prose lead">{!! $s('intro_body') !!}</div>
                @if($highlights->count())
                    <ul class="checks">@foreach($highlights->take(4) as $h)<li><span><b style="color:var(--ink)">{{ $h->title }}</b>@if($h->body) — {{ $h->body }}@endif</span></li>@endforeach</ul>
                @endif
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <a class="btn btn-primary" href="{{ isset($menu['about'][0]) ? route('site.page', $menu['about'][0]['slug']) : route('site.people') }}">Discover more</a>
                    <a class="btn btn-outline" href="{{ route('site.contact') }}#visit">Book a visit</a>
                </div>
            </div>
            <div class="media-frame reveal d2">
                @php $intro = $F($s('intro_image')) ?: $heroImage; @endphp
                @if($intro)<img src="{{ $intro }}" alt="Life at {{ $school->name }}" loading="lazy">@else<div style="aspect-ratio:4/4.2;border-radius:26px;background:var(--soft)"></div>@endif
                @if($s('founded_year'))<div class="badge"><b>{{ now()->year - (int) $s('founded_year') }}+</b>years of excellence</div>@endif
            </div>
        </div>
    </section>

    {{-- Stats --}}
    @if(count($stats))
        <section class="stats">
            <div class="container grid">
                @foreach($stats as $st)
                    <div class="stat reveal"><b data-count="{{ $st['value'] }}" data-suffix="{{ $st['suffix'] }}">{{ number_format($st['value']) }}{{ $st['suffix'] }}</b><span>{{ $st['label'] }}</span></div>
                @endforeach
            </div>
        </section>
    @endif

    {{-- Admission + upcoming events --}}
    <section class="section cream">
        <div class="container split">
            <div class="reveal">
                <span class="eyebrow">Admissions {{ now()->month >= 8 ? now()->year . '/' . (now()->year + 1) : (now()->year - 1) . '/' . now()->year }}</span>
                <h2>{{ $s('admission_open', '1') === '1' ? 'Admission is ongoing' : 'Join our school family' }}</h2>
                <div class="prose">{!! $s('admission_text') !!}</div>
                <div style="display:flex;gap:12px;flex-wrap:wrap;margin-top:10px">
                    <a class="btn btn-primary" href="{{ route('site.admissions') }}#apply">Start your application</a>
                    @foreach(array_slice($menu['admissions'] ?? [], 0, 2) as $p)
                        <a class="btn btn-outline" href="{{ route('site.page', $p['slug']) }}">{{ $p['title'] }}</a>
                    @endforeach
                </div>
            </div>
            <div class="reveal d2">
                <h3 style="margin-bottom:18px"><i class="ri-calendar-event-line" style="color:var(--primary)"></i> Upcoming events</h3>
                @if($events->count())
                    <div class="events">
                        @foreach($events as $e)
                            @php $d = \Carbon\Carbon::parse($e->date); @endphp
                            <a class="event" href="{{ $e->url }}">
                                <div class="date"><div><b>{{ $d->format('d') }}</b><small>{{ $d->format('M') }}</small></div></div>
                                <div><h4>{{ $e->title }}</h4><div class="where">{{ $d->format('l') }}{{ $e->time ? ' · ' . $e->time : '' }}{{ $e->location ? ' · ' . $e->location : '' }}</div></div>
                            </a>
                        @endforeach
                    </div>
                    <a class="link-arrow" style="margin-top:18px" href="{{ route('site.calendar') }}">Full school calendar <i class="ri-arrow-right-line"></i></a>
                @else
                    <div class="panel"><p class="muted" style="margin:0">No upcoming events right now. See the <a href="{{ route('site.calendar') }}">school calendar</a>.</p></div>
                @endif
            </div>
        </div>
    </section>

    {{-- Core values --}}
    @if($values->count())
        <section class="section">
            <div class="container">
                <div class="section-head center reveal">
                    <span class="eyebrow">Our core values</span>
                    <h2>{{ $s('values_title', 'Let your child be part of this exceptional growth system') }}</h2>
                </div>
                <div class="grid-3">
                    @foreach($values as $i => $v)
                        <div class="value reveal d{{ $i % 3 + 1 }}" data-n="{{ str_pad($i + 1, 2, '0', STR_PAD_LEFT) }}">
                            <i class="{{ $v->icon ?: 'ri-star-line' }}"></i>
                            <h3>{{ $v->title }}</h3>
                            @if($v->body)<p>{{ $v->body }}</p>@endif
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Gallery preview --}}
    @if($photos->count())
        <section class="section soft">
            <div class="container">
                <div class="section-head center reveal">
                    <span class="eyebrow">Gallery</span>
                    <h2>{{ $s('gallery_title', 'Life at ' . $school->name) }}</h2>
                </div>
                <div class="masonry reveal">
                    @foreach($photos as $x)
                        <a href="{{ $F($x['p']->path) }}" data-lightbox="home" data-caption="{{ $x['p']->caption ?: $x['a']->title }}">
                            <img src="{{ $F($x['p']->thumb ?: $x['p']->path) }}" alt="{{ $x['p']->caption ?: $x['a']->title }}" loading="lazy">
                        </a>
                    @endforeach
                </div>
                <div class="center" style="margin-top:26px"><a class="btn btn-outline" href="{{ route('site.gallery') }}">View the full gallery</a></div>
            </div>
        </section>
    @endif

    {{-- Testimonials --}}
    @if($testimonials->count())
        <section class="section">
            <div class="container">
                <div class="section-head center reveal">
                    <span class="eyebrow">Stories</span>
                    <h2>{{ $s('testimonials_title', 'What our families say') }}</h2>
                </div>
                <div class="quotes">
                    @foreach($testimonials->take(6) as $i => $t)
                        <div class="quote reveal d{{ $i % 3 + 1 }}">
                            <p>{{ $t->body }}</p>
                            <div class="who">
                                @if($t->image)<img src="{{ $F($t->image) }}" alt="">@else<span class="avatar">{{ mb_substr($t->title, 0, 1) }}</span>@endif
                                <div><b>{{ $t->title }}</b>@if($t->subtitle)<small>{{ $t->subtitle }}</small>@endif</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- News --}}
    @if($news->count())
        <section class="section soft">
            <div class="container">
                <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:20px;flex-wrap:wrap;margin-bottom:40px" class="reveal">
                    <div><span class="eyebrow">News &amp; updates</span><h2 style="margin:0">{{ $s('news_title', 'From around the school') }}</h2></div>
                    <a class="link-arrow" href="{{ route('site.news') }}">View all news <i class="ri-arrow-right-line"></i></a>
                </div>
                <div class="grid-3">
                    @foreach($news as $i => $p)
                        @include('website.partials.post-card', ['p' => $p, 'delay' => $i % 3 + 1])
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- Partners --}}
    @if($partners->count())
        <section class="section" style="padding:60px 0">
            <div class="container">
                <p class="center eyebrow" style="display:flex;justify-content:center">Our partners &amp; accreditations</p>
                <div class="partners reveal">
                    @foreach($partners as $pt)
                        @if($pt->link)<a href="{{ $pt->link }}" target="_blank" rel="noopener" title="{{ $pt->title }}"><img src="{{ $F($pt->image) }}" alt="{{ $pt->title }}"></a>
                        @else<img src="{{ $F($pt->image) }}" alt="{{ $pt->title }}" title="{{ $pt->title }}">@endif
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    {{-- App promo --}}
    <section class="section" style="padding-top:0">
        <div class="container">
            @include('website.partials.app-promo')
        </div>
    </section>

    {{-- FAQ + CTA --}}
    @if($faqs->count())
        <section class="section cream">
            <div class="container split" style="align-items:start">
                <div class="reveal">
                    <span class="eyebrow">FAQs</span>
                    <h2>{{ $s('faq_title', 'Frequently asked questions') }}</h2>
                    <p class="lead">{{ $s('faq_text', "Can't find what you're looking for? Our admissions team is happy to help.") }}</p>
                    <div style="display:flex;gap:12px;flex-wrap:wrap">
                        <a class="btn btn-primary" href="{{ route('site.contact') }}">Ask a question</a>
                        @if($school->phones)<a class="btn btn-outline" href="tel:{{ preg_replace('/\s+/', '', $school->phones[0]) }}"><i class="ri-phone-line"></i> {{ $school->phones[0] }}</a>@endif
                    </div>
                </div>
                <div class="faq reveal d2">
                    @foreach($faqs as $i => $f)
                        <details @if($i === 0) open @endif><summary>{{ $i + 1 }}. {{ $f->title }}</summary><div>{!! nl2br(e($f->body)) !!}</div></details>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="section" style="padding:70px 0">
        <div class="container">
            <div class="cta-band reveal">
                <div>
                    <h2>{{ $s('cta_title', 'Give your child the best start') }}</h2>
                    <p>{{ $s('cta_text', 'Visit our campus, meet our teachers and see learning in action.') }}</p>
                </div>
                <div style="display:flex;gap:12px;flex-wrap:wrap">
                    <a class="btn btn-light" href="{{ route('site.admissions') }}#apply">Apply now</a>
                    <a class="btn btn-ghost" href="{{ route('site.contact') }}#visit">Book a visit</a>
                </div>
            </div>
        </div>
    </section>
@endsection
