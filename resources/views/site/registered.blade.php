@extends('site.layout')
@section('title', 'Registration received')
@php($a = $application)

@section('content')
    <section class="s-section s-hero">
        <div class="s-wrap s-narrow">
            <i class="ri-checkbox-circle-fill" style="font-size:64px;color:#1db954"></i>
            @if($a->status === 'admitted')
                <h1 class="s-headline">You’re in.</h1>
                <p class="s-sub">Your admission number is <b>{{ $a->student->student_no }}</b>. We have e-mailed it to you with your classes.</p>
            @else
                <h1 class="s-headline">Thanks. We’ve got it.</h1>
                <p class="s-sub">We will review your registration and e-mail you. Once you are admitted you get your admission number and a link to pay.</p>
            @endif
            <p class="s-lead">Your reference is <b style="color:var(--s-ink)">{{ $a->reference }}</b>.</p>

            <div class="s-pay" style="margin-top:32px">
                <div class="s-pay-box">
                    <dl class="s-dl">
                        @foreach($a->choices as $choice)
                            <dt>{{ $choice->course->title }}</dt>
                            <dd>{{ $choice->cohort->name }}@if($a->status === 'admitted' && $choice->status !== 'admitted') · not offered @endif</dd>
                        @endforeach
                    </dl>
                    @if($invoice)
                        <div class="s-pay-total"><span>Amount due · {{ $invoice->number }}</span><b>{{ \App\Services\Website\Content::money($invoice->balance(), $invoice->currency) }}</b></div>
                        <a class="s-btn" style="width:100%;justify-content:center;margin-top:16px" href="{{ $payUrl }}">Pay now</a>
                        <p class="s-muted" style="font-size:13px;margin:12px 0 0;text-align:center">You can also pay later from the link in your e-mail or from your student account. Your receipt is e-mailed to you.</p>
                    @endif
                </div>
            </div>
            @if(!$invoice && $a->status === 'admitted')
                <p class="s-lead" style="margin-top:24px">We have e-mailed you how to sign in to your student account.</p>
            @endif
            <div class="s-ctas"><a class="s-more" href="{{ route('site.academy') }}">Browse other courses</a></div>
        </div>
    </section>
@endsection
