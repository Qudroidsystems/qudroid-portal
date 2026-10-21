@extends('website.layout', ['pageTitle' => $page->title, 'metaDescription' => $page->summary])

@php
    $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p);
    $groups = ['about' => 'About us', 'admissions' => 'Admissions', 'academics' => 'Academics', 'life' => 'School life'];
@endphp

@section('content')
    @include('website.partials.page-hero', [
        'title' => $page->title, 'lead' => $page->summary, 'image' => $F($page->banner),
        'crumbs' => array_values(array_filter([$page->menu && isset($groups[$page->menu]) ? [$groups[$page->menu], null] : null, [$page->title, null]])),
    ])
    <section class="section">
        <div class="container with-side">
            <article class="prose reveal">{!! $page->body !!}</article>
            <aside class="side">
                @if($siblings->count() > 1)
                    <div class="box">
                        <h4>{{ $groups[$page->menu] ?? 'More' }}</h4>
                        @foreach($siblings as $sb)
                            <a class="sib {{ $sb->slug === $page->slug ? 'on' : '' }}" href="{{ route('site.page', $sb->slug) }}">{{ $sb->title }} <i class="ri-arrow-right-s-line"></i></a>
                        @endforeach
                    </div>
                @endif
                <div class="box dark">
                    <h4>Admission is open</h4>
                    <p>Give your child a great start. Apply online or book a visit.</p>
                    <a class="btn btn-light btn-sm" href="{{ route('site.admissions') }}#apply">Apply now</a>
                </div>
                @include('website.partials.contact-box')
            </aside>
        </div>
    </section>
@endsection
