@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Portal-wide settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="Paystack" icon="ri-bank-card-line">
                <x-slot:tools>
                    @if($enabled)
                        <span class="badge {{ $mode === 'live' ? 'bg-success' : 'bg-warning text-dark' }}">{{ $mode === 'live' ? 'Live: taking real payments' : 'Test mode' }}</span>
                    @else
                        <span class="badge bg-secondary">Online payments off</span>
                    @endif
                </x-slot:tools>

                <form method="POST" action="{{ route('portal-settings.payments.save') }}" autocomplete="off">
                    @csrf
                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" role="switch" id="active" name="active" value="1" @checked(old('active', $c['active'] ?? false))>
                        <label class="form-check-label fw-semibold" for="active">Let people pay invoices online with Paystack</label>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold">Mode</label>
                        <div class="d-flex gap-3 flex-wrap">
                            @foreach(['sandbox' => 'Test (no real money)', 'live' => 'Live'] as $v => $label)
                                <div class="form-check"><input class="form-check-input" type="radio" name="mode" id="mode_{{ $v }}" value="{{ $v }}" @checked(old('mode', $mode) === $v)><label class="form-check-label" for="mode_{{ $v }}">{{ $label }}</label></div>
                            @endforeach
                        </div>
                    </div>

                    <div class="row g-3">
                        @foreach(['test' => 'Test keys', 'live' => 'Live keys'] as $m => $title)
                            <div class="col-md-6">
                                <div class="border rounded p-3 h-100">
                                    <h6 class="mb-3">{{ $title }}</h6>
                                    <label class="form-label small">Public key</label>
                                    <input name="{{ $m }}_public_key" class="form-control font-monospace mb-3" value="{{ old($m . '_public_key', $c[$m . '_public_key'] ?? '') }}" placeholder="pk_{{ $m }}_…" maxlength="200" spellcheck="false">
                                    <label class="form-label small">Secret key</label>
                                    <input type="password" name="{{ $m }}_secret_key" class="form-control font-monospace" placeholder="{{ $masked[$m] ? 'Saved: ' . $masked[$m] . ' (leave blank to keep)' : 'sk_' . $m . '_…' }}" maxlength="200" autocomplete="new-password" spellcheck="false">
                                    @if($masked[$m])
                                        <div class="small text-muted mt-1">{{ $saved[$m] ? 'Saved here, encrypted.' : 'Coming from .env.' }}</div>
                                        @if($saved[$m])
                                            <div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="clear_{{ $m }}_secret" value="1" id="clear_{{ $m }}"><label class="form-check-label small" for="clear_{{ $m }}">Remove the saved secret key</label></div>
                                        @endif
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="d-flex gap-2 mt-3 flex-wrap">
                        <button class="btn btn-primary"><i class="ri-save-line me-1"></i>Save</button>
                    </div>
                </form>

                <hr>
                <div class="d-flex gap-2 flex-wrap align-items-center">
                    <form method="POST" action="{{ route('portal-settings.payments.test') }}">@csrf
                        <button class="btn btn-soft-secondary"><i class="ri-plug-line me-1"></i>Test the {{ $mode === 'live' ? 'live' : 'test' }} key with Paystack</button>
                    </form>
                    @if($source === 'portal')
                        <form method="POST" action="{{ route('portal-settings.payments.env') }}" onsubmit="return confirm('Remove the Paystack settings saved here and use the .env values?')">@csrf
                            <button class="btn btn-link text-danger">Use the .env values instead</button>
                        </form>
                    @endif
                </div>
                @if($last)
                    <div class="small text-muted mt-2">Last changed {{ $last->updated_at->diffForHumans() }}{{ $last->editor ? ' by ' . $last->editor->name : '' }}.</div>
                @endif
            </x-cb.card>
        </div>

        <div class="col-xl-4">
            <x-cb.card title="Webhook" icon="ri-links-line">
                <p class="small">In your Paystack dashboard, open <strong>Settings → API Keys &amp; Webhooks</strong> and set the webhook URL (for Test and for Live) to:</p>
                <div class="input-group mb-2">
                    <input class="form-control font-monospace small" id="hookUrl" value="{{ route('webhooks.paystack') }}" readonly>
                    <button class="btn btn-soft-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('hookUrl').value); this.textContent='Copied'">Copy</button>
                </div>
                <p class="small text-muted mb-0">Paystack signs each webhook with your secret key, so there's no separate webhook secret to enter. Payments are only marked paid after the portal checks them with Paystack, never just because someone returns to a "success" page. The URL must be reachable from the internet (not <code>localhost</code>).</p>
            </x-cb.card>
            <x-cb.card title="Good to know" icon="ri-information-line">
                <ul class="small mb-0 ps-3">
                    <li>Secret keys are stored encrypted. If the app's <code>APP_KEY</code> ever changes, enter them again.</li>
                    <li>Anything left empty here falls back to the <code>PAYSTACK_*</code> values in <code>.env</code>.</li>
                    <li>Keys are in the Paystack dashboard under <strong>Settings → API Keys &amp; Webhooks</strong>.</li>
                </ul>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
