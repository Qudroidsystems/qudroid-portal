@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Mobile app download" icon="ri-smartphone-line" subtitle="Let parents, students and staff download the school's app from the website.">
        <x-slot:actions><a class="cb-hero-btn" target="_blank" href="{{ route('site.app') }}"><i class="ri-external-link-line me-1"></i>View app page</a></x-slot:actions>
    </x-cb.hero>
    @include('website.admin._nav')

    <form method="POST" action="{{ route('website.admin.app.save') }}" enctype="multipart/form-data">@csrf
        <div class="row g-3">
            <div class="col-xl-7">
                <x-cb.card title="App details" icon="ri-information-line">
                    <label class="form-label">App name</label><input name="app_name" class="form-control mb-2" value="{{ old('app_name', $s['app_name'] ?? '') }}">
                    <label class="form-label">Short description</label><textarea name="app_intro" class="form-control mb-2" rows="2" maxlength="400">{{ old('app_intro', $s['app_intro'] ?? '') }}</textarea>
                    <div class="row g-2">
                        <div class="col-md-4"><label class="form-label">Version</label><input name="app_version" class="form-control" value="{{ old('app_version', $s['app_version'] ?? '') }}" placeholder="1.0.0"></div>
                        <div class="col-md-8"><label class="form-label">Google Play link</label><input name="app_play_url" class="form-control" value="{{ old('app_play_url', $s['app_play_url'] ?? '') }}" placeholder="https://play.google.com/store/apps/details?id=…"></div>
                        <div class="col-md-12"><label class="form-label">App Store link (iPhone)</label><input name="app_ios_url" class="form-control" value="{{ old('app_ios_url', $s['app_ios_url'] ?? '') }}" placeholder="https://apps.apple.com/…"></div>
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-5">
                <x-cb.card title="Android APK (direct download)" icon="ri-android-line">
                    @if($apk)
                        <div class="cb-banner info mb-3"><i class="ri-checkbox-circle-line"></i><div>APK uploaded — {{ $apk['size'] }} MB, {{ $apk['at'] }}. <a href="{{ route('site.app.apk') }}">Test download</a></div></div>
                    @endif
                    <input type="file" name="apk" accept=".apk,application/vnd.android.package-archive" class="form-control">
                    <div class="form-text">Build it with <code>flutter build apk --release</code> and upload <code>app-release.apk</code>. Server upload limit: {{ $maxUpload }} — raise <code>upload_max_filesize</code> / <code>post_max_size</code> in PHP if the APK is larger.</div>
                    @if($apk)<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="remove_apk" value="1" id="rapk"><label class="form-check-label small" for="rapk">Remove the APK</label></div>@endif
                </x-cb.card>
                <x-cb.card title="App screens" icon="ri-smartphone-line">
                    @php $scr = \App\Models\Website\WebsiteItem::where('kind', 'app_screen')->count(); @endphp
                    <p class="small text-muted mb-2">Screenshots of the app, shown in phone frames on the home page and the Mobile app page.
                        {{ $scr ? $scr . ' feature' . ($scr === 1 ? '' : 's') . ' added.' : 'None yet — a drawn phone is shown instead.' }}</p>
                    <a href="{{ route('website.admin.list', 'appscreens') }}" class="btn btn-sm btn-outline-primary"><i class="ri-image-add-line me-1"></i>Manage app screens</a>
                </x-cb.card>
                <button class="btn btn-primary w-100"><i class="ri-save-3-line me-1"></i>Save</button>
            </div>
        </div>
    </form>
</div></div></div>
@endsection
