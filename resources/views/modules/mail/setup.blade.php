@extends('layouts.master')
@section('title', 'Set up Qmail')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Qmail" icon="ri-mail-line" subtitle="Your e-mail inside the portal: inbox, conversations, search, snooze, scheduled send.">
        @if($canManage)
            <x-slot:actions><a href="{{ route('mail.admin.index') }}" class="cb-hero-btn"><i class="ri-shield-user-line"></i>Manage mailboxes</a></x-slot:actions>
        @endif
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-5">
            <x-cb.card title="Get a company address" icon="ri-at-line">
                <p class="mb-2">An admin can give you an address{{ $server['domain'] ? ' like you@' . $server['domain'] : '' }}, or add you to a shared inbox such as support@ or sales@. It appears here as soon as they do.</p>
                @if($canManage)
                    <a href="{{ route('mail.admin.index') }}" class="btn btn-sm btn-primary">Create addresses</a>
                @else
                    <p class="text-muted small mb-0">Ask your admin for a Qmail address.</p>
                @endif
            </x-cb.card>
        </div>
        <div class="col-lg-7">
            <x-cb.card title="Or connect a mailbox you already have" icon="ri-link">
                <form method="POST" action="{{ route('mail.connect') }}">
                    @csrf
                    <div class="row g-2 mb-2">
                        <div class="col-md-6"><label class="form-label small mb-1">E-mail address</label><input name="email" type="email" class="form-control form-control-sm" value="{{ old('email') }}" required autocomplete="off"></div>
                        <div class="col-md-6"><label class="form-label small mb-1">Password or app password</label><input name="password" type="password" class="form-control form-control-sm" required autocomplete="new-password"></div>
                        <div class="col-md-6"><label class="form-label small mb-1">Your name on sent mail</label><input name="name" class="form-control form-control-sm" value="{{ old('name', auth()->user()->name) }}"></div>
                    </div>
                    @include('modules.mail._server', ['v' => []])
                    <p class="small text-muted mt-2 mb-2">We check the details before saving. The password is stored encrypted and only used to fetch and send your mail.</p>
                    <button class="btn btn-primary btn-sm">Connect mailbox</button>
                </form>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
