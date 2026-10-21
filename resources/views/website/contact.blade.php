@extends('website.layout', ['pageTitle' => 'Contact us'])

@php $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d; @endphp

@section('content')
    @include('website.partials.page-hero', ['title' => 'Contact us', 'lead' => 'Questions, a visit or admission enquiries — we would love to hear from you.', 'crumbs' => [['Contact', null]]])
    <section class="section">
        <div class="container split" style="align-items:start">
            <div class="reveal">
                <span class="eyebrow">Get in touch</span>
                <h2>We are here to help</h2>
                <div class="info-list" style="margin:26px 0">
                    @if($school->address)<div class="it"><i class="ri-map-pin-2-line"></i><div><b>Address</b>{{ $school->address }}</div></div>@endif
                    @foreach($school->phones as $ph)<div class="it"><i class="ri-phone-line"></i><div><b>Phone</b><a href="tel:{{ preg_replace('/\s+/', '', $ph) }}">{{ $ph }}</a></div></div>@endforeach
                    @if($school->email)<div class="it"><i class="ri-mail-line"></i><div><b>E-mail</b><a href="mailto:{{ $school->email }}">{{ $school->email }}</a></div></div>@endif
                    <div class="it"><i class="ri-time-line"></i><div><b>Office hours</b>{{ $s('office_hours', 'Monday – Friday, 8:00am – 4:00pm') }}</div></div>
                </div>
                @if($s('map_embed'))<div class="map">{!! $s('map_embed') !!}</div>@endif
            </div>
            <div class="reveal d2">
                <form class="panel form" method="POST" action="{{ route('site.contact.send') }}" id="visit">@csrf
                    <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
                    <h3 style="margin:0">Send a message or book a visit</h3>
                    <div class="chips" style="justify-content:flex-start;margin:0">
                        <label class="chip"><input type="radio" name="type" value="contact" {{ old('type', request('visit') ? 'visit' : 'contact') === 'contact' ? 'checked' : '' }} style="width:auto"> Message</label>
                        <label class="chip"><input type="radio" name="type" value="visit" {{ old('type') === 'visit' ? 'checked' : '' }} style="width:auto"> Book a school visit</label>
                    </div>
                    <div class="row2">
                        <div><label>Your name *</label><input name="name" required value="{{ old('name') }}"></div>
                        <div><label>Phone</label><input name="phone" value="{{ old('phone') }}" inputmode="tel"></div>
                    </div>
                    <div class="row2">
                        <div><label>E-mail</label><input type="email" name="email" value="{{ old('email') }}"></div>
                        <div><label>Preferred visit date</label><input type="date" name="visit_date" value="{{ old('visit_date') }}" min="{{ date('Y-m-d') }}"></div>
                    </div>
                    <div><label>Subject</label><input name="subject" value="{{ old('subject') }}"></div>
                    <div><label>Message *</label><textarea name="message" rows="5" required>{{ old('message') }}</textarea></div>
                    <div><button class="btn btn-primary" type="submit">Send <i class="ri-send-plane-line"></i></button></div>
                </form>
            </div>
        </div>
    </section>
@endsection
