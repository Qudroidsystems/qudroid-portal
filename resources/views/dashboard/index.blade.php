@extends('layouts.master')

@section('content')
<div class="main-content">
<div class="page-content">
<div class="container-fluid">

    @php
        $hour = now()->hour;
        $greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');
        $me = auth()->user();
    @endphp

    <x-cb.hero :title="$greeting . ', ' . $me->first_name" icon="ri-sun-line"
               :subtitle="$brand['tagline']">
        <x-slot:pills>
            <span class="cb-meta-pill"><i class="ri-user-3-line me-1"></i>{{ $me->user_type_label }}</span>
            @foreach($me->getRoleNames() as $r)
                <span class="cb-meta-pill"><i class="ri-shield-check-line me-1"></i>{{ $r }}</span>
            @endforeach
            <span class="cb-meta-pill"><i class="ri-calendar-line me-1"></i>{{ now()->format('l, j F Y') }}</span>
        </x-slot:pills>
        <x-slot:actions>
            <a href="{{ route('notifications.index') }}" class="cb-hero-btn"><i class="ri-notification-3-line"></i>Notifications @if($unread)<span class="badge bg-danger ms-1">{{ $unread }}</span>@endif</a>
            <a href="{{ route('profile.settings', $me->id) }}" class="cb-hero-btn"><i class="ri-settings-3-line"></i>My account</a>
        </x-slot:actions>
    </x-cb.hero>

    @if(session('success'))<div class="cb-banner info"><i class="ri-information-line"></i><div>{{ session('success') }}</div></div>@endif

    @if($overview)
        <div class="row g-3 mb-3">
            <div class="col-xl-3 col-md-6"><x-cb.stat label="People with accounts" :value="number_format($overview['users'])" icon="ri-team-line" accent="teal" :hint="$overview['newThisMonth'] . ' new this month'" /></div>
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Online now" :value="$overview['online']" icon="ri-user-follow-line" accent="green" /></div>
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Signed in today" :value="$overview['signins']" icon="ri-login-circle-line" accent="sky" /></div>
            <div class="col-xl-3 col-md-6"><x-cb.stat label="Roles" :value="$overview['roles']" icon="ri-shield-user-line" accent="amber" :hint="$overview['disabled'] ? $overview['disabled'] . ' disabled account(s)' : null" /></div>
        </div>
    @endif

    {{-- App launcher --}}
    <x-cb.card title="Your workspace" icon="ri-apps-2-line">
        <div class="row g-3">
            @foreach($modules as $m)
                @php $open = $m['live'] && $m['allowed']; @endphp
                @if($m['allowed'] || $me->isSuperAdmin())
                <div class="col-xxl-3 col-lg-4 col-sm-6">
                    @if($open)<a href="{{ $m['url'] }}" class="qh-app text-reset">@else<div class="qh-app is-soon" aria-disabled="true">@endif
                        <span class="qh-app-ico" style="--app: {{ $m['color'] ?? '#405189' }};"><i class="{{ $m['icon'] }}"></i></span>
                        <span class="qh-app-body">
                            <span class="qh-app-name">{{ $m['name'] }}
                                @unless($m['live'])<span class="qh-soon">Phase {{ $m['phase'] ?? '–' }} · coming soon</span>@endunless
                            </span>
                            <span class="qh-app-desc">{{ $m['description'] ?? '' }}</span>
                        </span>
                    @if($open)</a>@else</div>@endif
                </div>
                @endif
            @endforeach
            @if(collect($modules)->where('allowed', true)->isEmpty() && !$me->isSuperAdmin())
                <div class="col-12"><div class="empty-state"><i class="ri-apps-line"></i><h6>No modules yet</h6><p>Your administrator has not given your account access to a module.</p></div></div>
            @endif
        </div>
    </x-cb.card>

    <div class="row g-3">
        <div class="{{ $overview && $overview['recent']->isNotEmpty() ? 'col-xl-6' : 'col-12' }}">
            <x-cb.card title="Latest notifications" icon="ri-notification-3-line" :count="$unread ?: null" :flush="true">
                <x-slot:tools><a href="{{ route('notifications.index') }}" class="btn btn-sm btn-soft-primary">See all</a></x-slot:tools>
                @if($notifications->isEmpty())
                    <div class="empty-state"><i class="ri-notification-off-line"></i><h6>Nothing new</h6><p>You're all caught up.</p></div>
                @else
                    <div class="list-group list-group-flush">
                        @foreach($notifications as $n)
                            <a href="{{ route('notifications.open', $n->id) }}" class="list-group-item list-group-item-action d-flex gap-3 py-3">
                                <span class="qh-n-ico"><i class="{{ $n->data['icon'] ?? 'ri-notification-3-line' }}"></i></span>
                                <span class="flex-grow-1">
                                    <span class="d-flex justify-content-between gap-2"><strong class="{{ $n->read_at ? 'fw-normal' : '' }}">{{ $n->data['title'] ?? 'Notification' }}</strong><small class="text-muted text-nowrap">{{ $n->created_at->diffForHumans() }}</small></span>
                                    <span class="d-block small text-muted">{{ \Illuminate\Support\Str::limit($n->data['body'] ?? '', 120) }}</span>
                                </span>
                            </a>
                        @endforeach
                    </div>
                @endif
            </x-cb.card>
        </div>

        @if($overview && $overview['recent']->isNotEmpty())
        <div class="col-xl-6">
            <x-cb.card title="Recent activity" icon="ri-history-line" :flush="true">
                <x-slot:tools><a href="{{ route('activity.index') }}" class="btn btn-sm btn-soft-primary">Activity log</a></x-slot:tools>
                <div class="table-responsive">
                    <table class="table table-sm align-middle mb-0">
                        <tbody>
                            @foreach($overview['recent'] as $a)
                                <tr>
                                    <td class="ps-3"><span class="fw-semibold">{{ $a->name ?? 'Someone' }}</span><div class="small text-muted">{{ $a->description }}</div></td>
                                    <td class="text-end pe-3 small text-muted text-nowrap">{{ \Carbon\Carbon::parse($a->created_at)->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </x-cb.card>
        </div>
        @endif
    </div>

    @if($overview && $overview['types']->isNotEmpty())
        <x-cb.card title="Accounts by type" icon="ri-pie-chart-2-line">
            <div class="d-flex flex-wrap gap-2">
                @foreach(config('qudroid.user_types') as $key => $label)
                    <span class="qh-type"><strong>{{ number_format($overview['types'][$key] ?? 0) }}</strong> {{ $label }}</span>
                @endforeach
            </div>
        </x-cb.card>
    @endif

</div>
</div>
</div>

<style>
.qh-app { display:flex; gap:14px; align-items:flex-start; padding:16px; border:1px solid var(--vz-border-color,#e9ebec); border-radius:14px; height:100%; transition:transform .15s, box-shadow .15s, border-color .15s; background:var(--vz-secondary-bg,#fff); }
a.qh-app:hover { transform:translateY(-2px); box-shadow:0 10px 24px rgba(15,23,42,.08); border-color:var(--app,#405189); }
.qh-app.is-soon { opacity:.6; cursor:default; }
.qh-app-ico { width:46px; height:46px; border-radius:12px; flex-shrink:0; display:inline-flex; align-items:center; justify-content:center; font-size:22px; color:var(--app); background:color-mix(in srgb, var(--app) 12%, transparent); }
.qh-app-body { display:flex; flex-direction:column; gap:3px; min-width:0; }
.qh-app-name { font-weight:700; font-size:14px; display:flex; flex-wrap:wrap; align-items:center; gap:6px; }
.qh-app-desc { font-size:12.5px; color:var(--vz-secondary-color,#64748b); line-height:1.45; }
.qh-soon { font-size:10px; font-weight:700; text-transform:uppercase; letter-spacing:.3px; padding:2px 7px; border-radius:20px; background:#f1f5f9; color:#64748b; }
.qh-n-ico { width:36px; height:36px; border-radius:10px; background:rgba(64,81,137,.1); color:#405189; display:inline-flex; align-items:center; justify-content:center; font-size:17px; flex-shrink:0; }
.qh-type { padding:8px 14px; border-radius:10px; background:var(--vz-light,#f3f6f9); font-size:13px; }
</style>
@endsection
