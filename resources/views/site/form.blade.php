@extends('site.layout')
@section('title', $form->name)

@section('content')
    <section class="s-section" id="form-{{ $form->slug }}">
        <div class="s-wrap">
            <h1 class="s-h2">{{ $form->name }}</h1>
            @if($form->intro)<p class="s-lead">{{ $form->intro }}</p>@endif
            @include('site.partials.form', ['form' => $form])
        </div>
    </section>
@endsection
