@php
    $s = fn ($k, $d = null) => (isset($site[$k]) && $site[$k] !== '') ? $site[$k] : $d;
    $hasApk = $s('app_apk') && \Illuminate\Support\Facades\Storage::disk('public')->exists($s('app_apk'));
    $appFeatures = collect(preg_split('/\R/', (string) $s('app_features', "Results & report cards\nPay school fees online\nAttendance & timetable\nHomework & e-learning\nAI learning guidance\nNotices & reminders")))->map(fn ($f) => trim($f))->filter()->values();
    $promoShots = collect();
    try {
        $promoShots = \App\Models\Website\WebsiteItem::where('kind', 'app_screen')->where('published', true)->orderBy('sort')->orderBy('id')->get()
            ->flatMap(fn ($i) => collect($i->screens())->map(fn ($p) => [\App\Services\Website\WebsiteContent::fileUrl($p), $i->title]))->take(2)->values();
    } catch (\Throwable $e) {}
@endphp
<div class="app-promo reveal">
    <div>
        <span class="eyebrow" style="color:#9ef0c0">Mobile app</span>
        <h2>{{ $s('app_name', $school->name . ' App') }}</h2>
        <p>{{ $s('app_intro', 'Results, fees, attendance, timetable, homework and school news — in your pocket.') }}</p>
        <ul class="features">
            @foreach($appFeatures as $feat)
            <li><i class="ri-checkbox-circle-fill"></i> {{ $feat }}</li>
            @endforeach
        </ul>
        <div class="store-btns">
            @if($s('app_play_url'))<a class="store-btn" href="{{ $s('app_play_url') }}" target="_blank" rel="noopener"><i class="ri-google-play-fill"></i><span><small>GET IT ON</small><b>Google Play</b></span></a>@endif
            @if($s('app_ios_url'))<a class="store-btn" href="{{ $s('app_ios_url') }}" target="_blank" rel="noopener"><i class="ri-apple-fill"></i><span><small>Download on the</small><b>App Store</b></span></a>@endif
            @if($hasApk)<a class="store-btn apk" href="{{ route('site.app.apk') }}"><i class="ri-android-fill"></i><span><small>DIRECT DOWNLOAD</small><b>Android APK</b></span></a>@endif
            <a class="btn btn-ghost" href="{{ route('site.app') }}">How to install</a>
        </div>
    </div>
    @if($promoShots->count())
        <div class="device-pair {{ $promoShots->count() === 1 ? 'single' : '' }}" aria-hidden="true">
            @foreach($promoShots as $i => [$src, $alt])
                @include('website.partials.device', ['src' => $src, 'alt' => $alt, 'class' => $i === 0 ? 'back' : 'front'])
            @endforeach
        </div>
    @else
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
    @endif
</div>
