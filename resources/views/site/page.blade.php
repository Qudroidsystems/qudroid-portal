@extends('site.layout')
@if($page && $page->slug !== 'home')
    @section('title', $page->title)
@endif
@if($page?->description)
    @section('description', $page->description)
@endif

@section('content')
    @if($page && !$page->isPublished())<div class="s-ribbon">Draft preview. Only website editors can see this.</div>@endif
    @include('site.blocks.render', ['blocks' => $blocks])
@endsection
