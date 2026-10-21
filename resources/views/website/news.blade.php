@extends('website.layout', ['pageTitle' => $type === 'event' ? 'Events' : 'News & updates'])

@section('content')
    @include('website.partials.page-hero', [
        'title' => $type === 'event' ? 'Events' : ($type === 'story' ? 'Student stories' : 'News & updates'),
        'lead' => 'What is happening around the school — achievements, events and announcements.',
        'crumbs' => [['School life', null], [$type === 'event' ? 'Events' : 'News', null]],
    ])
    <section class="section">
        <div class="container">
            <div class="chips">
                <a class="chip {{ !$type && !request('category') ? 'on' : '' }}" href="{{ route('site.news') }}">All</a>
                <a class="chip {{ $type === 'news' ? 'on' : '' }}" href="{{ route('site.news', ['type' => 'news']) }}">News</a>
                <a class="chip {{ $type === 'event' ? 'on' : '' }}" href="{{ route('site.news', ['type' => 'event']) }}">Events</a>
                <a class="chip {{ $type === 'story' ? 'on' : '' }}" href="{{ route('site.news', ['type' => 'story']) }}">Student stories</a>
                @foreach($categories as $c)
                    <a class="chip {{ request('category') === $c ? 'on' : '' }}" href="{{ route('site.news', ['category' => $c]) }}">{{ $c }}</a>
                @endforeach
            </div>
            @if($posts->count())
                <div class="grid-3">
                    @foreach($posts as $i => $p) @include('website.partials.post-card', ['p' => $p, 'delay' => $i % 3 + 1]) @endforeach
                </div>
                @include('website.partials.pager', ['pg' => $posts])
            @else
                <div class="empty"><i class="ri-newspaper-line"></i>Nothing here yet — check back soon.</div>
            @endif
        </div>
    </section>
@endsection
