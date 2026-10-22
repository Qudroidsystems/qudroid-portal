@extends('layouts.master')

@section('content')
@php
    $c = $certificate;
    $grade = $c->get('grade');
@endphp
<div class="main-content"><div class="page-content"><div class="container-fluid">

    <div class="no-print">
        <x-cb.hero :title="$c->serial" icon="ri-award-line" :subtitle="$c->get('name') . ' · ' . $c->get('course')" :back="route('certificates.index')" back-label="Certificates">
            <x-slot:pills>
                <span class="cb-meta-pill">{{ $c->isValid() ? 'Valid' : 'Revoked' }}</span>
                <span class="cb-meta-pill">Issued {{ $c->issued_at->format('j M Y') }}</span>
                @if($manage)<span class="cb-meta-pill">Checked {{ $c->verify_count }} {{ \Illuminate\Support\Str::plural('time', $c->verify_count) }}</span>@endif
            </x-slot:pills>
            <x-slot:actions>
                @if($c->isValid())<button type="button" onclick="window.print()" class="cb-hero-btn"><i class="ri-printer-line"></i>Print or save as PDF</button>@endif
                <a href="{{ $c->verifyUrl() }}" target="_blank" class="cb-hero-btn"><i class="ri-shield-check-line"></i>Verify page</a>
            </x-slot:actions>
        </x-cb.hero>
        @include('partials.flash')
        @if(!$c->isValid())
            <div class="cb-banner danger"><i class="ri-close-circle-line"></i><div>Revoked {{ $c->revoked_at?->format('j M Y') }}@if($c->revoker) by {{ $c->revoker->name }}@endif: {{ $c->revoke_reason }}</div></div>
        @endif
        @if($manage && $c->get('override_note'))
            <div class="cb-banner info"><i class="ri-information-line"></i><div>Issued below the pass grade: {{ $c->get('override_note') }}</div></div>
        @endif
    </div>

    <div class="cert-sheet {{ $c->isValid() ? '' : 'cert-void' }}">
        <div class="cert-inner">
            <img src="{{ asset($brand['logo']) }}" alt="{{ $brand['company'] ?? 'Qudroid Systems' }}" class="cert-logo">
            <div class="cert-kicker">Certificate of Completion</div>
            <div class="cert-lead">This is to certify that</div>
            <div class="cert-name">{{ $c->get('name') }}</div>
            <div class="cert-lead">has successfully completed</div>
            <div class="cert-course">{{ $c->get('course') }}</div>
            <div class="cert-meta">
                {{ $c->get('cohort') }}
                @if($c->get('started_on')) · {{ \Illuminate\Support\Carbon::parse($c->get('started_on'))->format('M Y') }} to {{ \Illuminate\Support\Carbon::parse($c->get('completed_on'))->format('M Y') }}@endif
                @if($grade !== null) · Final grade {{ rtrim(rtrim(number_format((float) $grade, 1), '0'), '.') }}%@endif
            </div>
            <div class="cert-foot">
                <div class="cert-sign">
                    <div class="cert-line"></div>
                    <div class="fw-semibold">{{ config('qudroid.academy.certificate_signatory') }}</div>
                    <div class="small text-muted">{{ config('qudroid.academy.certificate_signatory_title') }}</div>
                </div>
                <div class="cert-date">
                    <div class="fw-semibold">{{ $c->issued_at->format('j F Y') }}</div>
                    <div class="small text-muted">Date issued</div>
                </div>
                <div class="cert-qr">
                    {!! $qr !!}
                    <div class="small">{{ $c->serial }}</div>
                    <div class="cert-tiny">Scan to verify</div>
                </div>
            </div>
            @unless($c->isValid())<div class="cert-stamp">REVOKED</div>@endunless
        </div>
    </div>

    @if($manage && $c->isValid())
        <div class="no-print mt-3" style="max-width: 600px">
            <x-cb.card title="Revoke this certificate" icon="ri-close-circle-line">
                <form method="POST" action="{{ route('certificates.revoke', $c) }}" onsubmit="return confirm('Revoke {{ $c->serial }}? The verify page will show it as revoked.')">@csrf
                    <div class="input-group"><input name="reason" class="form-control" required maxlength="300" placeholder="Reason (shown on the verify page)"><button class="btn btn-danger">Revoke</button></div>
                </form>
            </x-cb.card>
        </div>
    @endif

</div></div></div>
@endsection

@push('styles')
<style>
.cert-sheet { background: #fff; max-width: 1050px; aspect-ratio: 297 / 210; margin: 0 auto; padding: 22px; box-shadow: 0 6px 24px rgba(15, 23, 42, .08); border-radius: 6px; }
.cert-inner { position: relative; height: 100%; border: 3px double #6366f1; border-radius: 4px; padding: 4% 7%; display: flex; flex-direction: column; align-items: center; text-align: center; color: #0f172a; }
.cert-logo { height: 46px; margin-bottom: 2.5%; }
.cert-kicker { font-size: 13px; letter-spacing: .32em; text-transform: uppercase; color: #6366f1; font-weight: 600; margin-bottom: 3%; }
.cert-lead { color: #475569; font-size: 15px; }
.cert-name { font-family: Georgia, 'Times New Roman', serif; font-size: clamp(26px, 4.2vw, 44px); font-weight: 600; margin: 1.5% 0 2%; border-bottom: 1px solid #cbd5e1; padding: 0 30px 6px; }
.cert-course { font-size: clamp(18px, 2.6vw, 26px); font-weight: 600; margin: 1% 0 1.5%; }
.cert-meta { color: #475569; font-size: 14px; }
.cert-foot { margin-top: auto; width: 100%; display: flex; justify-content: space-between; align-items: flex-end; }
.cert-sign, .cert-date { width: 30%; }
.cert-line { border-top: 1px solid #0f172a; margin: 0 10% 6px; }
.cert-qr { width: 22%; display: flex; flex-direction: column; align-items: center; }
.cert-qr svg { width: 92px; height: 92px; }
.cert-tiny { font-size: 10px; color: #64748b; }
.cert-stamp { position: absolute; top: 40%; left: 50%; transform: translate(-50%, -50%) rotate(-18deg); font-size: 64px; font-weight: 800; color: rgba(220, 38, 38, .25); border: 6px solid rgba(220, 38, 38, .25); padding: 0 24px; }
.cert-void .cert-inner { border-color: #94a3b8; }
@media (max-width: 640px) { .cert-sheet { aspect-ratio: auto; } .cert-foot { flex-direction: column; align-items: center; gap: 16px; margin-top: 24px; } .cert-sign, .cert-date, .cert-qr { width: 100%; } }
@media print {
    @page { size: A4 landscape; margin: 0; }
    body * { visibility: hidden; }
    .cert-sheet, .cert-sheet * { visibility: visible; }
    .cert-sheet { position: fixed; inset: 0; max-width: none; width: 297mm; height: 210mm; box-shadow: none; border-radius: 0; padding: 10mm; }
}
</style>
@endpush
