@php
    $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d;
    $hasApk = $s('app_apk') && \Illuminate\Support\Facades\Storage::disk('public')->exists($s('app_apk'));
@endphp
<div class="app-promo reveal">
    <div>
        <span class="eyebrow" style="color:#9ef0c0">Mobile app</span>
        <h2>{{ $s('app_name', $school->name . ' App') }}</h2>
        <p>{{ $s('app_intro', 'Results, fees, attendance, timetable, homework and school news — in your pocket.') }}</p>
        <ul class="features">
            <li><i class="ri-checkbox-circle-fill"></i> Results &amp; report cards</li>
            <li><i class="ri-checkbox-circle-fill"></i> Pay school fees online</li>
            <li><i class="ri-checkbox-circle-fill"></i> Attendance &amp; timetable</li>
            <li><i class="ri-checkbox-circle-fill"></i> Homework &amp; e-learning</li>
            <li><i class="ri-checkbox-circle-fill"></i> AI learning guidance</li>
            <li><i class="ri-checkbox-circle-fill"></i> Notices &amp; reminders</li>
        </ul>
        <div class="store-btns">
            @if($s('app_play_url'))<a class="store-btn" href="{{ $s('app_play_url') }}" target="_blank" rel="noopener"><i class="ri-google-play-fill"></i><span><small>GET IT ON</small><b>Google Play</b></span></a>@endif
            @if($s('app_ios_url'))<a class="store-btn" href="{{ $s('app_ios_url') }}" target="_blank" rel="noopener"><i class="ri-apple-fill"></i><span><small>Download on the</small><b>App Store</b></span></a>@endif
            @if($hasApk)<a class="store-btn apk" href="{{ route('site.app.apk') }}"><i class="ri-android-fill"></i><span><small>DIRECT DOWNLOAD</small><b>Android APK</b></span></a>@endif
            <a class="btn btn-ghost" href="{{ route('site.app') }}">How to install</a>
        </div>
    </div>
    <div class="phone" aria-hidden="true">
        <div class="screen">
            <div class="bar"><small>Good morning</small><b>{{ \Illuminate\Support\Str::limit($school->name, 22) }}</b></div>
            <div class="tiles">
                @foreach([['ri-award-line', '#2563EB', 'Results'], ['ri-wallet-3-line', '#16A34A', 'Fees'], ['ri-checkbox-circle-line', '#0891B2', 'Attendance'], ['ri-time-line', '#D97706', 'Timetable'], ['ri-book-open-line', '#0D9488', 'Homework'], ['ri-sparkling-2-line', '#7C3AED', 'AI guide']] as [$ic, $c, $l])
                    <div class="tile"><i class="{{ $ic }}" style="background:{{ $c }}"></i>{{ $l }}</div>
                @endforeach
            </div>
            <div class="chart">@foreach([45, 60, 52, 70, 66, 82, 78] as $h)<span style="height:{{ $h }}%"></span>@endforeach</div>
        </div>
    </div>
</div>
