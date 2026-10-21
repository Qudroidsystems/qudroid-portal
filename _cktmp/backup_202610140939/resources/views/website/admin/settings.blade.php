@extends('layouts.master')

@php $v = fn ($k, $d = '') => old($k, $s[$k] ?? $d); $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p); @endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Website settings" icon="ri-settings-4-line" subtitle="Colours, the home page banner, welcome text, admissions notice, contacts and social media." />
    @include('website.admin._nav')

    <form method="POST" action="{{ route('website.admin.settings.save') }}" enctype="multipart/form-data">@csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Home page banner" icon="ri-image-2-line">
                    <p class="small text-muted">Shown when there are no slides in <a href="{{ route('website.admin.list', 'slides') }}">Home slider</a>; its photo is also the fallback for slides without one.</p>
                    <div class="row g-3">
                        <div class="col-md-7"><label class="form-label">Heading</label><input name="hero_title" class="form-control" value="{{ $v('hero_title') }}"></div>
                        <div class="col-md-5"><label class="form-label">Button text</label><input name="hero_button_text" class="form-control" value="{{ $v('hero_button_text') }}"></div>
                        <div class="col-md-7"><label class="form-label">Text under the heading</label><input name="hero_subtitle" class="form-control" value="{{ $v('hero_subtitle') }}"></div>
                        <div class="col-md-5"><label class="form-label">Button link</label><input name="hero_button_link" class="form-control" value="{{ $v('hero_button_link') }}" placeholder="/admissions#apply"></div>
                        <div class="col-md-12">
                            <label class="form-label">Banner photo</label>
                            <div class="d-flex gap-3 align-items-center">
                                @if(!empty($s['hero_image']))<img src="{{ $F($s['hero_image']) }}" style="width:160px;height:90px;object-fit:cover;border-radius:10px">@endif
                                <div class="flex-grow-1"><input type="file" name="hero_image" accept="image/*" class="form-control">
                                    @if(!empty($s['hero_image']))<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_hero_image" value="1" id="rh"><label class="form-check-label small" for="rh">Remove</label></div>@endif
                                    <div class="form-text">A wide photo (at least 1600 × 900). It is resized automatically.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-cb.card>

                <x-cb.card title="Welcome section" icon="ri-home-heart-line">
                    <label class="form-label">Heading</label>
                    <input name="intro_title" class="form-control mb-3" value="{{ $v('intro_title') }}">
                    <label class="form-label">Text</label>
                    @include('website.admin._editor', ['name' => 'intro_body', 'value' => $v('intro_body'), 'height' => 180])
                    <div class="row g-3 mt-1">
                        <div class="col-md-8">
                            <label class="form-label">Photo</label>
                            <input type="file" name="intro_image" accept="image/*" class="form-control">
                            @if(!empty($s['intro_image']))<div class="d-flex align-items-center gap-2 mt-2"><img src="{{ $F($s['intro_image']) }}" style="width:90px;height:90px;object-fit:cover;border-radius:10px"><div class="form-check"><input class="form-check-input" type="checkbox" name="remove_intro_image" value="1" id="ri"><label class="form-check-label small" for="ri">Remove</label></div></div>@endif
                        </div>
                        <div class="col-md-4"><label class="form-label">Year founded</label><input name="founded_year" type="number" class="form-control" value="{{ $v('founded_year') }}" placeholder="e.g. 1998"></div>
                    </div>
                </x-cb.card>

                <x-cb.card title="Admissions notice" icon="ri-user-add-line">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="admission_open" value="1" id="ao" @checked($v('admission_open', '1') === '1')><label class="form-check-label" for="ao">Show "Admission is ongoing"</label></div>
                    @include('website.admin._editor', ['name' => 'admission_text', 'value' => $v('admission_text'), 'height' => 160])
                    <label class="form-label mt-3">Core values heading</label>
                    <input name="values_title" class="form-control" value="{{ $v('values_title', 'Let your child be part of this exceptional growth system') }}">
                </x-cb.card>

                <x-cb.card title="Contacts & map" icon="ri-map-pin-line">
                    <p class="small text-muted">The school name, logo, address, phone numbers and e-mail come from <a href="{{ Route::has('schoolinformation.index') ? route('schoolinformation.index') : '#' }}">School information</a> ({{ $school->name }}).</p>
                    <div class="row g-3">
                        <div class="col-md-6"><label class="form-label">WhatsApp number</label><input name="whatsapp" class="form-control" value="{{ $v('whatsapp') }}" placeholder="0803…"></div>
                        <div class="col-md-6"><label class="form-label">Office hours</label><input name="office_hours" class="form-control" value="{{ $v('office_hours') }}" placeholder="Monday – Friday, 8am – 4pm"></div>
                        <div class="col-12"><label class="form-label">Google Maps embed code</label><textarea name="map_embed" class="form-control" rows="2" placeholder='<iframe src="https://www.google.com/maps/embed?pb=…"></iframe>'>{{ $v('map_embed') }}</textarea>
                            <div class="form-text">Google Maps › Share › Embed a map › Copy HTML.</div></div>
                    </div>
                </x-cb.card>
            </div>

            <div class="col-xl-4">
                <x-cb.card title="Website" icon="ri-global-line">
                    <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="enabled" value="1" id="en" @checked($v('enabled', '1') === '1')><label class="form-check-label fw-semibold" for="en">Website is live</label></div>
                    <p class="small text-muted mb-0">When off, the school's address opens the portal login page instead.</p>
                </x-cb.card>
                <x-cb.card title="Colours" icon="ri-palette-line">
                    @foreach(['primary_color' => ['Main colour', '#AE152D'], 'secondary_color' => ['Dark colour', '#0B3D2E'], 'accent_color' => ['Accent colour', '#2E9E5B']] as $k => [$label, $def])
                        <div class="d-flex align-items-center gap-2 mb-2">
                            <input type="color" class="form-control form-control-color" value="{{ $v($k, $def) }}" oninput="this.nextElementSibling.value=this.value">
                            <input name="{{ $k }}" class="form-control" value="{{ $v($k, $def) }}" pattern="#[0-9a-fA-F]{6}" style="max-width:120px">
                            <span class="small">{{ $label }}</span>
                        </div>
                    @endforeach
                    <div class="form-text">Use your school colours. Buttons and the top bar use the main colour.</div>
                </x-cb.card>
                <x-cb.card title="Numbers strip" icon="ri-numbers-line">
                    <div class="form-check form-switch"><input class="form-check-input" type="checkbox" role="switch" name="show_stats" value="1" id="ss" @checked($v('show_stats', '1') === '1')><label class="form-check-label" for="ss">Show live numbers (students, staff, classes, subjects)</label></div>
                </x-cb.card>
                <x-cb.card title="Social media" icon="ri-share-line">
                    @foreach(['social_facebook' => 'Facebook', 'social_instagram' => 'Instagram', 'social_x' => 'X (Twitter)', 'social_youtube' => 'YouTube', 'social_tiktok' => 'TikTok'] as $k => $label)
                        <label class="form-label small mb-1">{{ $label }}</label><input name="{{ $k }}" class="form-control form-control-sm mb-2" value="{{ $v($k) }}" placeholder="https://…">
                    @endforeach
                </x-cb.card>
                <x-cb.card title="Footer & search engines" icon="ri-search-eye-line">
                    <label class="form-label">Footer text</label><textarea name="footer_about" class="form-control mb-2" rows="2">{{ $v('footer_about') }}</textarea>
                    <label class="form-label">Description for Google</label><textarea name="meta_description" class="form-control" rows="2" maxlength="300">{{ $v('meta_description') }}</textarea>
                </x-cb.card>
                <button class="btn btn-primary w-100"><i class="ri-save-3-line me-1"></i>Save settings</button>
            </div>
        </div>
    </form>
</div></div></div>
@endsection
