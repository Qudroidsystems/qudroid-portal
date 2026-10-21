@extends('website.layout', ['pageTitle' => 'Mobile app'])

@php
    $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d;
    $hasApk = $s('app_apk') && \Illuminate\Support\Facades\Storage::disk('public')->exists($s('app_apk'));
    $size = $hasApk ? round(\Illuminate\Support\Facades\Storage::disk('public')->size($s('app_apk')) / 1048576, 1) : null;
    $portal = \App\Services\Website\WebsiteContent::portalUrl();
@endphp

@section('content')
    @include('website.partials.page-hero', ['title' => $s('app_name', $school->name . ' App'), 'lead' => $s('app_intro', 'Results, fees, attendance, timetable, homework and school news — in your pocket.'), 'crumbs' => [['Mobile app', null]]])

    <section class="section">
        <div class="container">@include('website.partials.app-promo')</div>
    </section>

    <section class="section soft">
        <div class="container">
            <div class="section-head center reveal">
                <span class="eyebrow">For everyone at school</span>
                <h2>What you can do in the app</h2>
            </div>
            <div class="grid-3">
                @foreach([
                    ['ri-parent-line', 'Parents', 'See each child\'s results, report cards and progress charts, pay fees, follow attendance and homework, message teachers and get AI learning guidance.'],
                    ['ri-graduation-cap-line', 'Students', 'Check your results, timetable, homework and calendar, and get a personal reading plan and career suggestions.'],
                    ['ri-presentation-line', 'Teachers', 'Enter scores (even offline), upload Excel score sheets, take attendance, see your timetable with lesson reminders, apply for leave and view payslips.'],
                ] as $i => [$ic, $t, $d])
                    <div class="value reveal d{{ $i + 1 }}" data-n="0{{ $i + 1 }}"><i class="{{ $ic }}"></i><h3>{{ $t }}</h3><p>{{ $d }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <section class="section">
        <div class="container split" style="align-items:start">
            <div class="reveal">
                <span class="eyebrow">Android</span>
                <h2>How to install on Android</h2>
                <ol class="steps" style="padding:0">
                    @if($s('app_play_url'))
                        <li><b>Open Google Play</b><br>Tap <a href="{{ $s('app_play_url') }}" target="_blank" rel="noopener">Get it on Google Play</a> and press <b>Install</b>. Updates arrive automatically.</li>
                    @endif
                    @if($hasApk)
                        <li><b>{{ $s('app_play_url') ? 'Or download' : 'Download' }} the APK</b><br>On your Android phone, tap <a href="{{ route('site.app.apk') }}">Download Android APK</a> (version {{ $s('app_version', '1.0') }}{{ $size ? ', ' . $size . ' MB' : '' }}).</li>
                        <li><b>Allow the install</b><br>When the download finishes, tap the file. If Android asks, allow <i>Install unknown apps</i> for your browser (Settings › Apps › your browser › Install unknown apps), then go back and tap <b>Install</b>.</li>
                    @endif
                    <li><b>Open the app and connect to the school</b><br>On the first screen, enter the school address: <code style="background:var(--soft);padding:2px 8px;border-radius:6px">{{ $portal }}</code></li>
                    <li><b>Sign in</b><br>Use the same e-mail / phone number and password as the school portal. Parents who don't have a login can ask the school office.</li>
                    <li><b>Allow notifications</b><br>So you receive results, fee reminders, notices and (for teachers) lesson reminders.</li>
                </ol>
            </div>
            <div class="reveal d2">
                <span class="eyebrow">iPhone</span>
                <h2>On iPhone &amp; iPad</h2>
                <ol class="steps" style="padding:0">
                    @if($s('app_ios_url'))
                        <li><b>Open the App Store</b><br>Tap <a href="{{ $s('app_ios_url') }}" target="_blank" rel="noopener">Download on the App Store</a> and press <b>Get</b>.</li>
                    @else
                        <li><b>Coming soon</b><br>The iPhone version is on its way. Meanwhile use the <a href="{{ \App\Services\Website\WebsiteContent::portalUrl('login') }}">web portal</a> in Safari — tap <b>Share › Add to Home Screen</b> for an app-like icon.</li>
                    @endif
                    <li><b>Connect &amp; sign in</b><br>Enter <code style="background:var(--soft);padding:2px 8px;border-radius:6px">{{ $portal }}</code> and sign in with your portal details.</li>
                </ol>
                <div class="note" style="margin-top:24px"><i class="ri-shield-check-line"></i> Only download the app from this page, Google Play or the App Store. The school will never ask for your password by phone or WhatsApp.</div>
                <div class="panel" style="margin-top:24px">
                    <h3>Need help?</h3>
                    <p>Can't sign in or don't have an account? Contact the school office and we will set you up.</p>
                    <a class="btn btn-primary btn-sm" href="{{ route('site.contact') }}">Contact us</a>
                </div>
            </div>
        </div>
    </section>
@endsection
