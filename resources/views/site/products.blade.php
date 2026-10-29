@extends('site.layout')
@section('title', 'Products')

@section('content')
    <section class="s-section s-hero">
        <div class="s-wrap">
            <h1 class="s-headline">Products</h1>
            <p class="s-sub">Software we build, run and support.</p>
        </div>
    </section>
    @include('site.blocks.tiles', ['b' => ['source' => 'products']])
    <section class="s-section" data-site-theme="grey">
        <div class="s-wrap">
            <h2 class="s-h2">All products.</h2>
            @if($products->isEmpty())
                <p class="s-lead">Our products will appear here soon.</p>
            @else
                <div class="s-stagger s-grid">@foreach($products as $p) @include('site.partials.product-card', ['p' => $p]) @endforeach</div>
            @endif
        </div>
    </section>
    @include('site.blocks.cta', ['b' => ['title' => 'Need something built for you?', 'text' => 'We also build custom software for clients.', 'button_label' => 'Start a project', 'url' => route('site.contact'), 'theme' => 'light']])
@endsection
