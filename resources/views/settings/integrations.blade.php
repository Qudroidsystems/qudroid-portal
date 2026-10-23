@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Settings" icon="ri-settings-4-line" subtitle="Portal-wide settings. Changes here take effect straight away." />
    @include('settings._tabs')
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-8">
            <x-cb.card title="GIFs in chat (GIPHY)" icon="ri-file-gif-line">
                <x-slot:tools>
                    @if($giphyHint)<span class="badge bg-success">On</span>@else<span class="badge bg-secondary">Off</span>@endif
                </x-slot:tools>
                <p class="text-muted">The GIF button in Connect searches GIPHY. Searches go through the portal, so the key is never shown to users. Results are limited to the PG rating; set <code>GIPHY_RATING</code> in .env to change it.</p>
                <form method="POST" action="{{ route('portal-settings.integrations.save') }}" autocomplete="off">
                    @csrf
                    <label class="form-label fw-semibold" for="giphy_api_key">GIPHY API key</label>
                    <input type="password" class="form-control mb-1" id="giphy_api_key" name="giphy_api_key" maxlength="100" placeholder="{{ $giphyHint ? 'Saved (' . $giphyHint . '). Leave blank to keep it.' : 'Paste your key' }}" autocomplete="new-password">
                    <div class="form-text mb-3">
                        @if($giphySaved) Saved in the portal (encrypted). @elseif($giphyEnv) Using <code>GIPHY_API_KEY</code> from .env. A key saved here would replace it. @else Not set. @endif
                        Get a free key at <a href="https://developers.giphy.com/dashboard/" target="_blank" rel="noopener">developers.giphy.com</a>: create an app and choose "API".
                    </div>
                    <div class="d-flex gap-2">
                        <button class="btn btn-primary">Save</button>
                        @if($giphySaved)<button class="btn btn-soft-danger" name="clear_giphy" value="1" onclick="return confirm('Remove the saved GIPHY key?')">Remove saved key</button>@endif
                    </div>
                </form>
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
