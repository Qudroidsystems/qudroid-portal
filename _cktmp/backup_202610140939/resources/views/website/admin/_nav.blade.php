{{-- Section tabs for the Website admin. --}}
@php
    $tabs = [
        ['website.admin.index', [], 'ri-dashboard-line', 'Overview'],
        ['website.admin.settings', [], 'ri-settings-4-line', 'Settings'],
        ['website.admin.list', ['slides'], 'ri-slideshow-3-line', 'Slider'],
        ['website.admin.list', ['pages'], 'ri-pages-line', 'Pages'],
        ['website.admin.list', ['posts'], 'ri-newspaper-line', 'News & events'],
        ['website.admin.albums', [], 'ri-image-2-line', 'Gallery'],
        ['website.admin.list', ['people'], 'ri-team-line', 'People'],
        ['website.admin.list', ['testimonials'], 'ri-chat-quote-line', 'Testimonials'],
        ['website.admin.list', ['values'], 'ri-medal-line', 'Values'],
        ['website.admin.list', ['highlights'], 'ri-star-smile-line', 'Highlights'],
        ['website.admin.list', ['faqs'], 'ri-question-answer-line', 'FAQs'],
        ['website.admin.list', ['partners'], 'ri-shake-hands-line', 'Partners'],
        ['website.admin.messages', [], 'ri-mail-line', 'Messages'],
        ['website.admin.app', [], 'ri-smartphone-line', 'Mobile app'],
    ];
    $unread = \Illuminate\Support\Facades\Schema::hasTable('website_messages') ? \App\Models\Website\WebsiteMessage::whereNull('read_at')->where('type', '!=', 'newsletter')->count() : 0;
@endphp
<div class="d-flex flex-wrap gap-2 mb-3 ws-tabs">
    @foreach($tabs as [$route, $params, $icon, $label])
        @php $href = route($route, $params); $on = url()->current() === $href || (count($params) && request()->route('type') === $params[0]); @endphp
        <a href="{{ $href }}" class="btn btn-sm {{ $on ? 'btn-primary' : 'btn-light' }}"><i class="{{ $icon }} me-1"></i>{{ $label }}
            @if($label === 'Messages' && $unread)<span class="badge bg-danger ms-1">{{ $unread }}</span>@endif
        </a>
    @endforeach
    <a href="{{ route('site.home') }}" target="_blank" class="btn btn-sm btn-outline-success ms-auto"><i class="ri-external-link-line me-1"></i>View website</a>
</div>
@if(session('success'))<div class="cb-banner info"><i class="ri-checkbox-circle-line"></i><div>{{ session('success') }}</div></div>@endif
@if(session('error'))<div class="cb-banner warning"><i class="ri-error-warning-line"></i><div>{{ session('error') }}</div></div>@endif
@if($errors->any())<div class="cb-banner warning"><i class="ri-error-warning-line"></i><div>{{ $errors->first() }}</div></div>@endif
