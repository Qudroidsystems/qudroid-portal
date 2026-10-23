@extends('layouts.master')
@section('title', 'Mailboxes')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Mailboxes" icon="ri-shield-user-line" subtitle="Hand out company addresses, set up shared inboxes and choose who can use each one." :back="route('mail.index')" back-label="Qmail">
        <x-slot:actions><a href="{{ route('portal-settings.mail') }}" class="cb-hero-btn"><i class="ri-settings-3-line"></i>Mail server settings</a></x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    @if(!$stalwart)
        <div class="alert alert-info small">
            To create new <strong>{{ '@' . ($server['domain'] ?: 'yourcompany.com') }}</strong> addresses from here, connect your Stalwart mail server under
            <a href="{{ route('portal-settings.mail') }}">Settings → Mail</a>. You can still add mailboxes that already exist on any server.
        </div>
    @endif

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card title="Add a mailbox" icon="ri-add-line">
                @php $how = old('how', $stalwart ? 'create' : 'connect'); @endphp
                <ul class="nav nav-pills nav-sm mb-3" role="tablist">
                    <li class="nav-item"><button type="button" class="nav-link py-1 {{ $how === 'create' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#mbCreate" @disabled(!$stalwart)>New address</button></li>
                    <li class="nav-item"><button type="button" class="nav-link py-1 {{ $how === 'connect' ? 'active' : '' }}" data-bs-toggle="pill" data-bs-target="#mbConnect">Existing mailbox</button></li>
                </ul>
                <div class="tab-content">
                    <div class="tab-pane fade {{ $how === 'create' ? 'show active' : '' }}" id="mbCreate">
                        <form method="POST" action="{{ route('mail.admin.store') }}">
                            @csrf <input type="hidden" name="how" value="create">
                            <label class="form-label small mb-1">Address</label>
                            <div class="input-group input-group-sm mb-2">
                                <input name="local" class="form-control" value="{{ old('local') }}" placeholder="ada or support" required>
                                <span class="input-group-text">{{ '@' . ($server['domain'] ?: 'domain') }}</span>
                            </div>
                            <label class="form-label small mb-1">Display name</label>
                            <input name="name" class="form-control form-control-sm mb-2" value="{{ old('name') }}" placeholder="Ada Lovelace or Qudroid Support">
                            <label class="form-label small mb-1">Type</label>
                            <select name="kind" class="form-select form-select-sm mb-2">
                                <option value="personal" @selected(old('kind') === 'personal')>Personal (one person)</option>
                                <option value="shared" @selected(old('kind') === 'shared')>Shared inbox (a team)</option>
                            </select>
                            <label class="form-label small mb-1">Storage limit in MB <span class="text-muted">(0 = no limit)</span></label>
                            <input name="quota_mb" type="number" min="0" class="form-control form-control-sm mb-2" value="{{ old('quota_mb', 0) }}">
                            <label class="form-label small mb-1">Who uses it</label>
                            @include('modules.mail._people')
                            <button class="btn btn-primary btn-sm mt-3">Create address</button>
                        </form>
                    </div>
                    <div class="tab-pane fade {{ $how === 'connect' ? 'show active' : '' }}" id="mbConnect">
                        <form method="POST" action="{{ route('mail.admin.store') }}">
                            @csrf <input type="hidden" name="how" value="connect">
                            <div class="row g-2 mb-2">
                                <div class="col-12"><label class="form-label small mb-1">E-mail address</label><input name="email" type="email" class="form-control form-control-sm" value="{{ old('email') }}" required autocomplete="off"></div>
                                <div class="col-12"><label class="form-label small mb-1">Password or app password</label><input name="password" type="password" class="form-control form-control-sm" required autocomplete="new-password"></div>
                                <div class="col-12"><label class="form-label small mb-1">Display name</label><input name="name" class="form-control form-control-sm" value="{{ old('name') }}"></div>
                                <div class="col-12"><select name="kind" class="form-select form-select-sm"><option value="personal">Personal (one person)</option><option value="shared" @selected(old('kind') === 'shared')>Shared inbox (a team)</option></select></div>
                            </div>
                            @include('modules.mail._server', ['v' => []])
                            <label class="form-label small mb-1 mt-2">Who uses it</label>
                            @include('modules.mail._people')
                            <button class="btn btn-primary btn-sm mt-3">Add mailbox</button>
                        </form>
                    </div>
                </div>
            </x-cb.card>
        </div>

        <div class="col-xl-8">
            <x-cb.card title="All mailboxes" icon="ri-mail-settings-line" :count="$accounts->count()" :flush="true">
                @if($accounts->isEmpty())
                    <div class="p-4 text-center text-muted">No mailboxes yet.</div>
                @else
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <thead><tr><th>Address</th><th>People</th><th class="text-end">Messages</th><th>Status</th><th></th></tr></thead>
                        <tbody>
                        @foreach($accounts as $a)
                            <tr>
                                <td>
                                    <div class="fw-semibold">{{ $a->email }}</div>
                                    <small class="text-muted">{{ $a->name }} · {{ $a->kind === 'shared' ? 'Shared inbox' : 'Personal' }}{{ $a->provisioned ? ' · created here' : ' · ' . $a->imap_host }}</small>
                                </td>
                                <td class="small">{{ $a->users->pluck('name')->take(4)->implode(', ') }}@if($a->users->count() > 4) +{{ $a->users->count() - 4 }}@endif</td>
                                <td class="text-end small">{{ number_format($a->messages_count) }}</td>
                                <td class="small">
                                    @if($a->status === 'ok')<span class="badge bg-success-subtle text-success">Working</span>
                                    @elseif($a->status === 'error')<span class="badge bg-danger-subtle text-danger" title="{{ $a->last_error }}">Problem</span>
                                    @else<span class="badge bg-light text-body">Not checked yet</span>@endif
                                    @if($a->last_synced_at)<div class="text-muted">{{ $a->last_synced_at->diffForHumans() }}</div>@endif
                                </td>
                                <td class="text-end text-nowrap">
                                    <div class="dropdown">
                                        <button class="btn btn-sm btn-light" data-bs-toggle="dropdown" aria-label="Actions for {{ $a->email }}"><i class="ri-more-2-fill"></i></button>
                                        <div class="dropdown-menu dropdown-menu-end">
                                            <button class="dropdown-item" data-bs-toggle="modal" data-bs-target="#access{{ $a->id }}"><i class="ri-team-line me-2"></i>Who can use it</button>
                                            <form method="POST" action="{{ route('mail.admin.check', $a) }}">@csrf<button class="dropdown-item"><i class="ri-refresh-line me-2"></i>Check connection</button></form>
                                            @if($a->provisioned)
                                                <form method="POST" action="{{ route('mail.admin.password', $a) }}" onsubmit="return confirm('Set a new password for {{ $a->email }}? Phones or mail apps using the old one will need the new one.')">@csrf<button class="dropdown-item"><i class="ri-key-2-line me-2"></i>New password (for a mail app)</button></form>
                                            @endif
                                            <div class="dropdown-divider"></div>
                                            <button class="dropdown-item text-danger" data-bs-toggle="modal" data-bs-target="#remove{{ $a->id }}"><i class="ri-delete-bin-line me-2"></i>Remove</button>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
                @endif
            </x-cb.card>
        </div>
    </div>

    @foreach($accounts as $a)
        <div class="modal fade" id="access{{ $a->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('mail.admin.members', $a) }}">
                @csrf @method('PUT')
                <div class="modal-header"><h6 class="modal-title">Who can use {{ $a->email }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <label class="form-label small mb-1">Display name</label>
                    <input name="name" class="form-control form-control-sm mb-2" value="{{ $a->name }}">
                    @include('modules.mail._people', ['selected' => $a->users->pluck('id')->all()])
                </div>
                <div class="modal-footer"><button class="btn btn-primary btn-sm">Save</button></div>
            </form>
        </div></div></div>
        <div class="modal fade" id="remove{{ $a->id }}" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content">
            <form method="POST" action="{{ route('mail.admin.destroy', $a) }}">
                @csrf @method('DELETE')
                <div class="modal-header"><h6 class="modal-title">Remove {{ $a->email }}</h6><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
                <div class="modal-body">
                    <p class="small">This removes the mailbox and its copy of the mail from the portal.</p>
                    @if($a->provisioned)
                        <div class="form-check mb-2"><input class="form-check-input" type="checkbox" name="delete_on_server" value="1" id="dos{{ $a->id }}"><label class="form-check-label small" for="dos{{ $a->id }}">Also delete the address and all its mail from the mail server (cannot be undone)</label></div>
                    @else
                        <p class="small text-muted">The mail stays on {{ $a->imap_host }}.</p>
                    @endif
                    <label class="form-label small mb-1">Type <strong>{{ $a->email }}</strong> to confirm</label>
                    <input name="confirm" class="form-control form-control-sm" required autocomplete="off">
                </div>
                <div class="modal-footer"><button class="btn btn-danger btn-sm">Remove</button></div>
            </form>
        </div></div></div>
    @endforeach
</div></div></div>
@endsection
