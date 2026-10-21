@extends('website.layout', ['pageTitle' => 'Our people'])

@php
    $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p);
    $titles = ['leadership' => 'School leadership', 'board' => 'Board & governance', 'staff' => 'Teachers & staff'];
@endphp

@section('content')
    @include('website.partials.page-hero', ['title' => 'Our people', 'lead' => 'Meet the dedicated team that guides, teaches and cares for our learners.', 'crumbs' => [['About', null], ['Our people', null]]])
    <section class="section">
        <div class="container">
            @forelse(['leadership', 'board', 'staff'] as $g)
                @if(isset($people[$g]))
                    <div class="section-head center reveal" style="margin-bottom:34px"><span class="eyebrow">{{ $titles[$g] }}</span></div>
                    <div class="grid-4" style="margin-bottom:60px">
                        @foreach($people[$g] as $i => $p)
                            <div class="person reveal d{{ $i % 4 + 1 }}">
                                <div class="ph">
                                    @if($p->photo)<img src="{{ $F($p->photo) }}" alt="{{ $p->name }}" loading="lazy">
                                    @else<span class="initials">{{ collect(explode(' ', $p->name))->filter()->map(fn ($w) => mb_substr($w, 0, 1))->take(2)->implode('') }}</span>@endif
                                </div>
                                <h4>{{ $p->name }}</h4>
                                @if($p->role)<small>{{ $p->role }}</small>@endif
                                @if($p->bio)<p style="font-size:.93rem;margin-top:8px">{{ \Illuminate\Support\Str::limit($p->bio, 160) }}</p>@endif
                            </div>
                        @endforeach
                    </div>
                @endif
            @empty
            @endforelse
            @if($people->isEmpty())<div class="empty"><i class="ri-team-line"></i>Our team page is being prepared.</div>@endif
        </div>
    </section>
@endsection
