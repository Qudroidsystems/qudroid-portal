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

                <x-cb.card title="Home page text" icon="ri-text-block">
                    <p class="small text-muted">The three cards under the banner, section headings and the closing call-to-action. Leave a box empty to use the standard wording.</p>
                    <div class="row g-3">
                        @foreach([1 => ['Academics', 'Classes, subjects and how our learners grow.'], 2 => ['Admission ongoing', "Start your child's application online today."], 3 => ['Our mobile app', 'Results, fees, attendance and news on your phone.']] as $n => [$dt, $dx])
                            <div class="col-md-5"><label class="form-label">Card {{ $n }} title</label><input name="quick{{ $n }}_title" class="form-control" value="{{ $v('quick' . $n . '_title', $dt) }}"></div>
                            <div class="col-md-7"><label class="form-label">Card {{ $n }} text</label><input name="quick{{ $n }}_text" class="form-control" value="{{ $v('quick' . $n . '_text', $dx) }}"></div>
                        @endforeach
                        <div class="col-md-6"><label class="form-label">Gallery heading</label><input name="gallery_title" class="form-control" value="{{ $v('gallery_title', 'Life at ' . $school->name) }}"></div>
                        <div class="col-md-6"><label class="form-label">Testimonials heading</label><input name="testimonials_title" class="form-control" value="{{ $v('testimonials_title', 'What our families say') }}"></div>
                        <div class="col-md-6"><label class="form-label">News heading</label><input name="news_title" class="form-control" value="{{ $v('news_title', 'From around the school') }}"></div>
                        <div class="col-md-6"><label class="form-label">FAQ heading</label><input name="faq_title" class="form-control" value="{{ $v('faq_title', 'Frequently asked questions') }}"></div>
                        <div class="col-12"><label class="form-label">FAQ intro</label><input name="faq_text" class="form-control" value="{{ $v('faq_text', "Can't find what you're looking for? Our admissions team is happy to help.") }}"></div>
                        <div class="col-md-5"><label class="form-label">Closing banner heading</label><input name="cta_title" class="form-control" value="{{ $v('cta_title', 'Give your child the best start') }}"></div>
                        <div class="col-md-7"><label class="form-label">Closing banner text</label><input name="cta_text" class="form-control" value="{{ $v('cta_text', 'Visit our campus, meet our teachers and see learning in action.') }}"></div>
                        <div class="col-12"><label class="form-label">Mobile app features (one per line)</label><textarea name="app_features" class="form-control" rows="6">{{ $v('app_features', "Results & report cards\nPay school fees online\nAttendance & timetable\nHomework & e-learning\nAI learning guidance\nNotices & reminders") }}</textarea></div>
                    </div>
                </x-cb.card>

                <x-cb.card title="School name, logo & contacts" icon="ri-building-line">
                    <p class="small text-muted">The website shows the portal's <i>School information</i> when it can read it. These are used when it can't (for example on a separately hosted website), so the header, footer and numbers are never empty.</p>
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Website logo</label>
                            <div class="d-flex gap-3 align-items-center">
                                <img src="{{ $F(!empty($s['website_logo']) ? $s['website_logo'] : null) ?: $school->logo }}" alt="" style="height:64px;max-width:160px;object-fit:contain;background:#f4f5f7;border-radius:10px;padding:6px">
                                <div class="flex-grow-1"><input type="file" name="website_logo" accept="image/*" class="form-control">
                                    @if(!empty($s['website_logo']))<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_website_logo" value="1" id="rwl"><label class="form-check-label small" for="rwl">Remove (use the School information logo)</label></div>@endif
                                    <div class="form-text">Leave empty to use the logo from School information.</div></div>
                            </div>
                        </div>
                        <div class="col-md-6"><label class="form-label">School name</label><input name="school_name" class="form-control" value="{{ $v('school_name') }}"></div>
                        <div class="col-md-6"><label class="form-label">Motto</label><input name="school_motto" class="form-control" value="{{ $v('school_motto') }}"></div>
                        <div class="col-12"><label class="form-label">Address</label><input name="school_address" class="form-control" value="{{ $v('school_address') }}"></div>
                        <div class="col-md-6"><label class="form-label">E-mail</label><input name="school_email" class="form-control" value="{{ $v('school_email') }}"></div>
                        <div class="col-md-6"><label class="form-label">Phone numbers (separate with commas)</label><input name="school_phones" class="form-control" value="{{ $v('school_phones') }}"></div>
                        @foreach(['stats_students' => 'Students', 'stats_staff' => 'Teachers & staff', 'stats_classes' => 'Classes', 'stats_subjects' => 'Subjects'] as $k => $label)
                            <div class="col-6 col-md-3"><label class="form-label">{{ $label }}</label><input name="{{ $k }}" type="number" min="0" class="form-control" value="{{ $v($k) }}"></div>
                        @endforeach
                    </div>
                </x-cb.card>

                <x-cb.card title="Main menu" icon="ri-menu-line">
                    <p class="small text-muted">One item per line as <code>Label | address</code>, in the order they appear. Address can be a page of this website (<code>/news</code>), a full <code>https://…</code> link, or a dropdown of pages: <code>@about</code>, <code>@admissions</code>, <code>@academics</code>, <code>@life</code> (the pages you put under that menu in Pages). Delete a line to hide an item.</p>
                    <textarea name="main_menu" class="form-control" rows="8">{{ $v('main_menu', "Home | /\nAbout | @about\nAdmissions | @admissions\nAcademics | @academics\nSchool life | @life\nMobile app | /app\nContact | /contact") }}</textarea>
                </x-cb.card>

                <x-cb.card title="Top bar links" icon="ri-links-line">
                    <p class="small text-muted">One link per line as <code>Label | address</code>. Use <code>/news</code> for pages of this website or a full <code>https://…</code> address. "Portal login" is always added at the end.</p>
                    <textarea name="topbar_links" class="form-control" rows="5">{{ $v('topbar_links', "Apply | /admissions#apply\nVisit us | /contact#visit\nNews & updates | /news\nGallery | /gallery") }}</textarea>
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

                <x-cb.card title="Domains" icon="ri-links-line">
                    @php $W = \App\Services\Website\WebsiteContent::class; @endphp
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Website domain</label>
                            <input name="website_domain" class="form-control" value="{{ $v('website_domain') }}" placeholder="www.csskabba.ng">
                            <div class="form-text">The school's main address. Works with and without "www".</div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Portal address</label>
                            <input name="portal_url" class="form-control" value="{{ $v('portal_url') }}" placeholder="https://portal.csskabba.ng">
                            <div class="form-text">Where "Portal login" on the website goes. Empty = {{ rtrim(config('app.url'), '/') }}.</div>
                        </div>
                    </div>
                    <div class="cb-banner info mt-3 mb-0"><i class="ri-information-line"></i><div class="small">
                        @if($W::websiteDomain())
                            <b>Live set-up:</b> <code>{{ $W::websiteDomain() }}</code> shows the website; any other address of this portal opens the login page at <code>/</code>,
                            and website pages opened on the portal's address are sent to the website domain.
                        @else
                            <b>No website domain yet.</b> The portal's <code>/</code> opens the login page, and you can preview the website at
                            <a href="{{ url('/website-preview') }}" target="_blank">{{ url('/website-preview') }}</a>.
                        @endif
                        <br><b>To connect the domain:</b> 1) In your DNS, point <code>@</code> and <code>www</code> (A record) to this server's IP.
                        2) In your hosting panel, add the domain as an <i>alias</i>/<i>parked domain</i> of the portal (same folder — <code>public</code>), or add a virtual host with the same document root.
                        3) Install an SSL certificate for both names. 4) Save the domain here.
                    </div></div>
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
