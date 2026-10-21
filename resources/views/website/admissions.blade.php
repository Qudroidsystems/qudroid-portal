@extends('website.layout', ['pageTitle' => 'Admissions'])

@php
    $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d;
    $classOptions = collect($classes)->flatten()->unique()->values();
@endphp

@section('content')
    @include('website.partials.page-hero', ['title' => 'Admissions', 'lead' => 'We welcome curious, motivated learners. Here is how to join our school family.', 'crumbs' => [['Admissions', null]]])

    <section class="section">
        <div class="container split" style="align-items:start">
            <div class="reveal">
                <span class="eyebrow">{{ $s('admission_open', '1') === '1' ? 'Admission ongoing' : 'Admissions' }}</span>
                <h2>Join us in four simple steps</h2>
                <ol class="steps" style="padding:0;margin:24px 0">
                    <li><b>Apply</b><br>Fill the online form below, or pick up a form at the school office.</li>
                    <li><b>Assessment</b><br>Your child sits a short entrance assessment appropriate to the class.</li>
                    <li><b>Interview</b><br>Meet with the admissions team; bring previous report cards.</li>
                    <li><b>Offer &amp; registration</b><br>Accept the offer, pay fees and receive your portal and app login.</li>
                </ol>
                <div class="prose">{!! $s('admission_text') !!}</div>
            </div>
            <div class="reveal d2">
                @foreach($pages as $p)
                    <a class="event" href="{{ route('site.page', $p->slug) }}" style="margin-bottom:12px">
                        <div class="date" style="background:var(--secondary)"><i class="ri-file-text-line" style="font-size:1.6rem"></i></div>
                        <div><h4>{{ $p->title }}</h4><div class="where">{{ $p->summary }}</div></div>
                    </a>
                @endforeach
                @if($faqs->count())
                    <h3 style="margin:30px 0 16px">Questions parents ask</h3>
                    <div class="faq">@foreach($faqs->take(5) as $i => $f)<details><summary>{{ $f->title }}</summary><div>{!! nl2br(e($f->body)) !!}</div></details>@endforeach</div>
                @endif
            </div>
        </div>
    </section>

    <section class="section cream" id="apply">
        <div class="container" style="max-width:900px">
            <div class="section-head center reveal">
                <span class="eyebrow">Apply online</span>
                <h2>Application form</h2>
                <p class="lead">Takes about two minutes. The admissions office will call you to arrange the assessment.</p>
            </div>
            <form class="panel form reveal" method="POST" action="{{ route('site.apply') }}">@csrf
                <input type="text" name="website" class="hp" tabindex="-1" autocomplete="off">
                <h3 style="margin:0">About the child</h3>
                <div class="row2">
                    <div><label>Child's full name *</label><input name="child_name" required value="{{ old('child_name') }}"></div>
                    <div><label>Class applying for *</label>
                        @if($classOptions->count())
                            <select name="class_sought" required><option value="">Choose…</option>@foreach($classOptions as $c)<option @selected(old('class_sought') === $c)>{{ $c }}</option>@endforeach</select>
                        @else<input name="class_sought" required value="{{ old('class_sought') }}" placeholder="e.g. JSS 1">@endif
                    </div>
                </div>
                <div class="row2">
                    <div><label>Date of birth</label><input type="date" name="date_of_birth" value="{{ old('date_of_birth') }}"></div>
                    <div><label>Gender</label><select name="gender"><option value="">—</option><option @selected(old('gender') === 'Male')>Male</option><option @selected(old('gender') === 'Female')>Female</option></select></div>
                </div>
                <div><label>Current / previous school</label><input name="current_school" value="{{ old('current_school') }}"></div>
                <h3 style="margin:10px 0 0">Parent or guardian</h3>
                <div class="row2">
                    <div><label>Full name *</label><input name="name" required value="{{ old('name') }}"></div>
                    <div><label>Phone number *</label><input name="phone" required value="{{ old('phone') }}" inputmode="tel"></div>
                </div>
                <div><label>E-mail</label><input type="email" name="email" value="{{ old('email') }}"></div>
                <div><label>Anything we should know?</label><textarea name="message" rows="4">{{ old('message') }}</textarea></div>
                <div><button class="btn btn-primary" type="submit">Submit application <i class="ri-send-plane-line"></i></button></div>
            </form>
        </div>
    </section>
@endsection
