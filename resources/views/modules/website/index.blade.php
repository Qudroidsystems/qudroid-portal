@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Website" icon="ri-global-line" subtitle="Your public website at {{ url('/') }}. Everything on it is edited here.">
        <x-slot:actions>
            <a class="cb-hero-btn" href="{{ route('site.home') }}" target="_blank"><i class="ri-external-link-line me-1"></i>View website</a>
        </x-slot:actions>
        <x-slot:pills>
            <span class="cb-meta-pill">{{ $cfg['enabled'] ? 'Live at /' : 'Switched off: / goes to sign in' }}</span>
        </x-slot:pills>
    </x-cb.hero>
    @include('partials.flash')

    @if(!$counts['pages'])
        <div class="alert alert-primary d-flex gap-3 align-items-center flex-wrap">
            <i class="ri-magic-line fs-3"></i>
            <div class="flex-grow-1"><strong>Start with ready-made content.</strong> Adds a home page in the apple.com style, an about page, your four products, two sample case studies (as drafts) and the contact form. You can change all of it.</div>
            <form method="POST" action="{{ route('website.starter') }}">@csrf<button class="btn btn-primary">Add starter content</button></form>
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach([
            ['Pages', $counts['pages'], 'ri-pages-line', route('website.pages.index')],
            ['Products', $counts['products'], 'ri-stack-line', route('website.products.index')],
            ['Our work', $counts['works'], 'ri-briefcase-4-line', route('website.works.index')],
            ['New form entries', $counts['new'], 'ri-inbox-line', route('website.forms.index')],
            ['Pictures', $counts['media'], 'ri-image-line', route('website.media.index')],
        ] as [$label, $n, $icon, $url])
            <div class="col-6 col-md"><a href="{{ $url }}" class="d-block text-body"><x-cb.stat :label="$label" :value="$n" :icon="$icon" /></a></div>
        @endforeach
    </div>

    <div class="row g-3">
        <div class="col-xl-7">
            <x-cb.card title="Latest form entries" icon="ri-inbox-line" :flush="true">
                <x-slot:tools><a href="{{ route('website.forms.index') }}" class="btn btn-sm btn-soft-primary">All forms</a></x-slot:tools>
                @forelse($entries as $e)
                    <a href="{{ route('website.forms.entries', $e->form) }}" class="d-flex gap-3 border-top px-3 py-2 text-body align-items-center">
                        <i class="ri-{{ $e->status === 'new' ? 'mail-unread-line text-primary' : 'mail-open-line text-muted' }} fs-5"></i>
                        <div class="flex-grow-1 text-truncate"><span class="fw-medium">{{ $e->name ?: $e->email ?: 'Someone' }}</span> <span class="text-muted">· {{ $e->form?->name }}</span></div>
                        <small class="text-muted text-nowrap">{{ $e->created_at->diffForHumans() }}</small>
                    </a>
                @empty
                    <div class="p-3 text-muted small">Nothing yet. Entries from the contact form and your other forms show here, and in CRM as new leads when the form is set to.</div>
                @endforelse
            </x-cb.card>
            <x-cb.card title="Pages" icon="ri-pages-line" :flush="true" class="mt-3">
                <x-slot:tools><a href="{{ route('website.pages.create') }}" class="btn btn-sm btn-primary"><i class="ri-add-line"></i> New page</a></x-slot:tools>
                @foreach($pages as $p)
                    <div class="d-flex gap-3 border-top px-3 py-2 align-items-center">
                        <a href="{{ route('website.pages.edit', $p) }}" class="fw-medium flex-grow-1">{{ $p->title }}</a>
                        <code class="small">/{{ $p->slug === 'home' ? '' : $p->slug }}</code>
                        <span class="badge bg-{{ $p->isPublished() ? 'success' : 'secondary' }}-subtle text-{{ $p->isPublished() ? 'success' : 'secondary' }}">{{ \App\Models\Website\Page::STATUSES[$p->status] ?? $p->status }}</span>
                    </div>
                @endforeach
            </x-cb.card>
        </div>
        <div class="col-xl-5">
            <x-cb.card title="Courses on the website" icon="ri-book-open-line" :flush="true">
                <x-slot:tools>@can('Manage academy')<a href="{{ route('academy.courses.index') }}" class="btn btn-sm btn-soft-primary">Academy courses</a>@endcan</x-slot:tools>
                @forelse($courses as $c)
                    <div class="border-top px-3 py-2">
                        <a href="{{ route('site.academy.course', $c->slug) }}" target="_blank" class="fw-medium">{{ $c->title }}</a>
                        <div class="small text-muted">{{ $c->cohorts->count() }} open {{ \Illuminate\Support\Str::plural('class', $c->cohorts->count()) }} · {{ $c->instant_enrollment ? 'pays at registration' : 'registrations reviewed first' }}</div>
                    </div>
                @empty
                    <div class="p-3 small text-muted">No course is taking registrations. A course shows here when it is published and has a cohort open for applications.</div>
                @endforelse
                <div class="border-top p-3 small text-muted">Turn on <strong>Take payment at registration</strong> on a course (Academy → Courses → Edit) to admit people straight away and send them to pay online.</div>
            </x-cb.card>
            <x-cb.card title="Online payment" icon="ri-bank-card-line" class="mt-3">
                <p class="small text-muted mb-2">Registrations and invoices are paid with the gateways switched on in Settings → Payments.</p>
                @php $gw = app(\App\Services\Finance\PaymentService::class)->onlineGateways(); @endphp
                @forelse($gw as $k => $label)<span class="badge bg-success-subtle text-success me-1"><i class="ri-check-line"></i> {{ $label }}</span>@empty<span class="badge bg-warning-subtle text-warning">No gateway is switched on</span>@endforelse
                @can('Manage settings')<div class="mt-2"><a href="{{ route('portal-settings.payments') }}" class="small">Payment settings</a></div>@endcan
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
