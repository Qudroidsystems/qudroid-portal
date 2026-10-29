@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Website settings" icon="ri-settings-3-line" subtitle="Name, contact details, menu and social links." :back="route('website.index')" back-label="Website" />
    @include('partials.flash')

    <form method="POST" action="{{ route('website.settings.save') }}">
        @csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="About the site" icon="ri-information-line">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">Name</label><input name="name" class="form-control" required maxlength="80" value="{{ old('name', $cfg['name']) }}"><div class="form-text">Shown beside the Qlouds logo and in page titles.</div></div>
                        <div class="col-md-6"><label class="form-label">Tagline</label><input name="tagline" class="form-control" maxlength="160" value="{{ old('tagline', $cfg['tagline']) }}"></div>
                        <div class="col-12"><label class="form-label">Description</label><textarea name="description" class="form-control" rows="2" maxlength="300">{{ old('description', $cfg['description']) }}</textarea><div class="form-text">Used by search engines and when links are shared.</div></div>
                        <div class="col-md-7"><label class="form-label">Announcement bar (optional)</label><input name="announcement" class="form-control" maxlength="160" value="{{ old('announcement', $cfg['announcement']) }}" placeholder="e.g. New cohort of Web Foundations starts 1 December"></div>
                        <div class="col-md-5"><label class="form-label">Announcement link</label><input name="announcement_url" class="form-control" maxlength="300" value="{{ old('announcement_url', $cfg['announcement_url']) }}" placeholder="/courses"></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Contact details" icon="ri-contacts-line" class="mt-3">
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">E-mail</label><input name="email" type="email" class="form-control" maxlength="190" value="{{ old('email', $cfg['email']) }}"></div>
                        <div class="col-md-6"><label class="form-label">Phone</label><input name="phone" class="form-control" maxlength="40" value="{{ old('phone', $cfg['phone']) }}"></div>
                        <div class="col-12"><label class="form-label">Address</label><input name="address" class="form-control" maxlength="300" value="{{ old('address', $cfg['address']) }}"></div>
                        <div class="col-12"><label class="form-label">Footer note</label><textarea name="footer_note" class="form-control" rows="2" maxlength="500">{{ old('footer_note', $cfg['footer_note']) }}</textarea><div class="form-text">Small print above the footer links, e.g. registration number.</div></div>
                    </div>
                </x-cb.card>
                <x-cb.card title="Social links" icon="ri-share-line" class="mt-3">
                    <div class="row g-3">
                        @foreach(\App\Services\Website\Site::SOCIALS as $k => $label)
                            <div class="col-md-6"><label class="form-label">{{ $label }}</label><input name="socials[{{ $k }}]" type="url" class="form-control" maxlength="300" placeholder="https://" value="{{ old("socials.$k", $cfg['socials'][$k] ?? '') }}"></div>
                        @endforeach
                    </div>
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Front page" icon="ri-home-4-line">
                    <div class="form-check form-switch"><input type="hidden" name="enabled" value="0"><input class="form-check-input" type="checkbox" id="wsOn" name="enabled" value="1" @checked(old('enabled', $cfg['enabled']))><label class="form-check-label" for="wsOn">Show the website at <code>/</code></label></div>
                    <div class="form-text">Off: <code>/</code> goes to the sign-in page as before.</div>
                </x-cb.card>
                <x-cb.card title="Top menu" icon="ri-menu-line" class="mt-3">
                    @foreach(['products' => 'Products', 'work' => 'Our work', 'academy' => 'Courses', 'contact' => 'Contact'] as $k => $label)
                        <div class="form-check form-switch mb-1"><input class="form-check-input" type="checkbox" id="nav{{ $k }}" name="nav[{{ $k }}]" value="1" @checked(old("nav.$k", $cfg['nav'][$k] ?? true))><label class="form-check-label" for="nav{{ $k }}">{{ $label }}</label></div>
                    @endforeach
                    <div class="form-text">Pages you mark "Show in the top menu" are added after these.</div>
                </x-cb.card>
                <button class="btn btn-primary w-100 mt-3">Save settings</button>
            </div>
        </div>
    </form>
</div></div></div>
@endsection
