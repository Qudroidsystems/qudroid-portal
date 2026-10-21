@extends('website.layout', ['pageTitle' => 'School calendar'])

@php
    $items = collect($events)->map(fn ($e) => (object) ['title' => $e->title, 'start' => \Carbon\Carbon::parse($e->start_date), 'end' => $e->end_date ? \Carbon\Carbon::parse($e->end_date) : null,
        'time' => !empty($e->all_day) ? 'All day' : ($e->start_time ? substr($e->start_time, 0, 5) : null), 'location' => $e->location, 'desc' => $e->description, 'url' => null])
        ->merge($posts->map(fn ($p) => (object) ['title' => $p->title, 'start' => $p->event_date, 'end' => null, 'time' => $p->event_time, 'location' => $p->location, 'desc' => $p->excerpt, 'url' => route('site.post', $p->slug)]))
        ->sortBy(fn ($x) => $x->start)->values();
@endphp

@section('content')
    @include('website.partials.page-hero', ['title' => 'School calendar', 'lead' => 'Term dates, examinations, holidays and school events.', 'crumbs' => [['Academics', route('site.academics')], ['Calendar', null]]])
    <section class="section">
        <div class="container" style="max-width:900px">
            <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:28px;gap:12px">
                <a class="btn btn-outline btn-sm" href="{{ route('site.calendar', ['month' => $month->copy()->subMonth()->format('Y-m')]) }}"><i class="ri-arrow-left-s-line"></i> {{ $month->copy()->subMonth()->format('M') }}</a>
                <h2 style="margin:0">{{ $month->format('F Y') }}</h2>
                <a class="btn btn-outline btn-sm" href="{{ route('site.calendar', ['month' => $month->copy()->addMonth()->format('Y-m')]) }}">{{ $month->copy()->addMonth()->format('M') }} <i class="ri-arrow-right-s-line"></i></a>
            </div>
            @if($items->count())
                <div class="events">
                    @foreach($items as $e)
                        @php $inner = view('website.partials.event-row', ['e' => $e])->render(); @endphp
                        @if($e->url)<a href="{{ $e->url }}" class="event reveal">{!! $inner !!}</a>@else<div class="event reveal">{!! $inner !!}</div>@endif
                    @endforeach
                </div>
            @else
                <div class="empty"><i class="ri-calendar-line"></i>No public events this month.</div>
            @endif
        </div>
    </section>
@endsection
