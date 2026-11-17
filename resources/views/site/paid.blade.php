@extends('site.layout')
@section('title', 'Payment')
@php($money = \App\Services\Website\Content::money($payment->amount, $payment->currency))

@section('content')
    <section class="s-section s-hero">
        <div class="s-wrap s-narrow">
            @if($payment->status === 'confirmed')
                <i class="ri-checkbox-circle-fill" style="font-size:64px;color:#1db954"></i>
                <h1 class="s-headline">Payment received.</h1>
                @php($courses = $payment->invoice?->enrollments->map(fn ($e) => $e->course?->title)->filter()->implode(', '))
                <p class="s-sub">Thank you. We received {{ $money }}@if($courses) for {{ $courses }}@endif.</p>
                <p class="s-lead">Receipt {{ $payment->number ?: $payment->reference }}. We have e-mailed it to you. Sign in to see your receipts and your courses.</p>
                <div class="s-ctas">
                    @if($payment->number)<a class="s-btn" href="{{ \App\Services\Finance\ReceiptMailer::downloadUrl($payment) }}"><i class="ri-download-2-line"></i> Download receipt</a>@endif
                    <a class="s-btn is-ghost" href="{{ route('login') }}">Sign in</a>
                </div>
            @elseif($problem || $payment->status === 'pending')
                <i class="ri-time-line" style="font-size:64px;color:#f5a623"></i>
                <h1 class="s-headline">Almost there.</h1>
                <p class="s-sub">We could not confirm your payment yet. If you were charged it will show on your account shortly. There is no need to pay again.</p>
                <div class="s-ctas"><a class="s-btn is-ghost" href="{{ request()->fullUrl() }}">Check again</a></div>
            @else
                <i class="ri-close-circle-fill" style="font-size:64px;color:#e30000"></i>
                <h1 class="s-headline">Payment not completed.</h1>
                <p class="s-sub">The payment did not go through, and you have not been charged for it.</p>
                <div class="s-ctas"><a class="s-btn" href="{{ url()->previous() !== url()->current() ? url()->previous() : route('site.home') }}">Try again</a></div>
            @endif
            <p class="s-muted" style="font-size:13px;margin-top:24px">Reference {{ $payment->reference }}</p>
        </div>
    </section>
@endsection
