@extends('site.layout')
@section('title', 'Contact')

@section('content')
    <section class="s-section s-hero">
        <div class="s-wrap">
            <h1 class="s-headline">Let’s talk.</h1>
            <p class="s-sub">{{ $form->intro }}</p>
        </div>
    </section>
    <section class="s-section is-tight" data-site-theme="grey" id="form-{{ $form->slug }}">
        <div class="s-wrap">
            @if($cfg['email'] || $cfg['phone'] || $cfg['address'])
                <div class="s-features" style="margin-top:0;margin-bottom:24px">
                    @if($cfg['email'])<div class="s-feature"><i class="ri-mail-line"></i><h4>E-mail</h4><p><a href="mailto:{{ $cfg['email'] }}">{{ $cfg['email'] }}</a></p></div>@endif
                    @if($cfg['phone'])<div class="s-feature"><i class="ri-phone-line"></i><h4>Phone</h4><p><a href="tel:{{ preg_replace('/[^0-9+]/', '', $cfg['phone']) }}">{{ $cfg['phone'] }}</a></p></div>@endif
                    @if($cfg['address'])<div class="s-feature"><i class="ri-map-pin-line"></i><h4>Visit</h4><p>{{ $cfg['address'] }}</p></div>@endif
                </div>
            @endif
            @include('site.partials.form', ['form' => $form])
        </div>
    </section>
@endsection
