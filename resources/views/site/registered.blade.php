@extends('site.layout')
@section('title', 'Registration received')
@php($a = $application)

@section('content')
    <section class="s-section s-hero">
        <div class="s-wrap s-narrow">
            <i class="ri-checkbox-circle-fill" style="font-size:64px;color:#1db954"></i>
            @if($a->status === 'admitted')
                <h1 class="s-headline">You’re in.</h1>
                <p class="s-sub">Your place on {{ $a->course->title }} ({{ $a->cohort->name }}) is reserved.</p>
            @else
                <h1 class="s-headline">Thanks. We’ve got it.</h1>
                <p class="s-sub">We will review your registration for {{ $a->course->title }} and get back to you by e-mail.</p>
            @endif
            <p class="s-lead">Your reference is <b style="color:var(--s-ink)">{{ $a->reference }}</b>.</p>

            @if($invoice)
                <div class="s-pay" style="margin-top:40px">
                    <div class="s-pay-box">
                        <dl class="s-dl">
                            <dt>Invoice</dt><dd>{{ $invoice->number }}</dd>
                            <dt>For</dt><dd>{{ $invoice->title }}</dd>
                        </dl>
                        <div class="s-pay-total"><span>Amount due</span><b>{{ \App\Services\Website\Content::money($invoice->balance(), $invoice->currency) }}</b></div>
                        <a class="s-btn" style="width:100%;justify-content:center;margin-top:16px" href="{{ $payUrl }}">Pay now</a>
                        <p class="s-muted" style="font-size:13px;margin:12px 0 0;text-align:center">You can also pay later from your student account. We have e-mailed you how to sign in.</p>
                    </div>
                </div>
            @elseif($a->status === 'admitted')
                <p class="s-lead">We have e-mailed you how to sign in to your student account.</p>
            @endif
            <div class="s-ctas"><a class="s-more" href="{{ route('site.academy') }}">Browse other courses</a></div>
        </div>
    </section>
@endsection
