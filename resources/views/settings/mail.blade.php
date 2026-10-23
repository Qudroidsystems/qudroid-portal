@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Portal-wide settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    <form method="POST" action="{{ route('portal-settings.mail.save') }}" autocomplete="off">
        @csrf
        <div class="row g-3">
            <div class="col-xl-6">
                <x-cb.card title="Company mail server" icon="ri-mail-settings-line">
                    <p class="text-muted small">Used for addresses created from the portal and offered as "Our mail server" when someone connects a mailbox. Leave a box empty to use the value from .env (QMAIL_*).</p>
                    <label class="form-label small mb-1">Mail domain</label>
                    <input name="domain" class="form-control form-control-sm mb-3" value="{{ old('domain', $server['domain']) }}" placeholder="qudroid.com">
                    @include('modules.mail._server', ['v' => $server])
                </x-cb.card>
            </div>
            <div class="col-xl-6">
                <x-cb.card title="Stalwart (create addresses from the portal)" icon="ri-server-line">
                    <x-slot:tools>@if($tokenHint && $server['stalwart_url'])<span class="badge bg-success">Connected</span>@else<span class="badge bg-secondary">Off</span>@endif</x-slot:tools>
                    <p class="text-muted small">Stalwart is the open-source mail server we recommend hosting for {{ $server['domain'] ?: 'your domain' }}. With its admin URL and an API key here, admins can create and remove addresses under Qmail → Mailboxes.</p>
                    <label class="form-label small mb-1">Stalwart admin URL</label>
                    <input name="stalwart_url" type="url" class="form-control form-control-sm mb-2" value="{{ old('stalwart_url', $server['stalwart_url']) }}" placeholder="https://mail.qudroid.com">
                    <label class="form-label small mb-1">API key</label>
                    <input name="stalwart_token" type="password" class="form-control form-control-sm" placeholder="{{ $tokenHint ? 'Saved (' . $tokenHint . '). Leave blank to keep it.' : 'Paste the API key' }}" autocomplete="new-password">
                    <div class="form-text">Stored encrypted. In Stalwart: Management → API keys, with permission to manage principals.</div>
                    @if($tokenSaved)<div class="form-check mt-2"><input class="form-check-input" type="checkbox" name="clear_token" value="1" id="clearTok"><label class="form-check-label small" for="clearTok">Remove the saved key</label></div>@endif
                </x-cb.card>
            </div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">Save</button>
            <button class="btn btn-soft-primary" formaction="{{ route('portal-settings.mail.test') }}">Test saved Stalwart connection</button>
        </div>
    </form>
</div></div></div>
@endsection
