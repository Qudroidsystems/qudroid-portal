@extends('modules.mail.layout')

@section('mail')
<div class="p-3 p-lg-4" style="max-width: 820px">
    <h5 class="mb-1">Settings for {{ $account->email }}</h5>
    <p class="text-muted small">{{ $account->kind === 'shared' ? 'A shared inbox: the name and signature are the same for everyone who uses it.' : 'Your mailbox.' }}</p>

    <form method="POST" action="{{ route('mail.settings.update', $account) }}">
        @csrf @method('PUT')
        <div class="mb-3">
            <label class="form-label">Name on sent mail</label>
            <input name="name" class="form-control" value="{{ old('name', $account->name) }}" maxlength="150">
        </div>
        <div class="mb-3">
            <label class="form-label">Signature</label>
            <textarea name="signature" class="form-control" rows="4" maxlength="3000" placeholder="Ada Lovelace&#10;Engineering Lead, Qudroid Systems">{{ old('signature', $account->signature) }}</textarea>
            <div class="form-text">Added under new messages and replies.</div>
        </div>
        @if($accounts->count() > 1)
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="is_default" value="1" id="isDefault" @checked($pivot->is_default)>
                <label class="form-check-label" for="isDefault">Open this mailbox first</label>
            </div>
        @endif
        @if($owner)
            <h6 class="mt-4">Server</h6>
            <div class="mb-2"><label class="form-label small mb-1">New password <span class="text-muted">(leave empty to keep)</span></label><input name="password" type="password" class="form-control form-control-sm" autocomplete="new-password"></div>
            @include('modules.mail._server', ['v' => $account, 'server' => \App\Services\Mail\MailService::server()])
        @endif
        <button class="btn btn-primary mt-3">Save</button>
    </form>

    <h6 class="mt-5">Keyboard shortcuts</h6>
    <div class="row small text-muted">
        <div class="col-md-6"><kbd>c</kbd> compose · <kbd>/</kbd> search · <kbd>j</kbd>/<kbd>k</kbd> next/previous · <kbd>o</kbd> open · <kbd>u</kbd> back to list · <kbd>x</kbd> select</div>
        <div class="col-md-6"><kbd>e</kbd> archive · <kbd>#</kbd> delete · <kbd>!</kbd> spam · <kbd>s</kbd> star · <kbd>b</kbd> snooze · <kbd>r</kbd>/<kbd>a</kbd>/<kbd>f</kbd> reply/all/forward · <kbd>Ctrl</kbd>+<kbd>Enter</kbd> send</div>
    </div>

    @if($owner)
        <form method="POST" action="{{ route('mail.disconnect', $account) }}" class="mt-5" onsubmit="return confirm('Remove this mailbox from the portal? The mail stays on its server.')">
            @csrf @method('DELETE')
            <button class="btn btn-outline-danger btn-sm"><i class="ri-link-unlink me-1"></i>Remove mailbox from the portal</button>
        </form>
    @endif
</div>
@endsection
