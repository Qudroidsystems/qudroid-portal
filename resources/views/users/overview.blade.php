@extends('layouts.master')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            @php $isSelf = $user->id === auth()->id(); $canEdit = $isSelf || auth()->user()->can('Update user'); @endphp

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">{{ $isSelf ? 'My Profile' : 'User Overview — ' . $user->name }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                @can('View user')
                                    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                                @endcan
                                <li class="breadcrumb-item active">Overview</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row">
                <div class="col-xxl-12">

                    <!-- Tabs & Navigation -->
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-4">
                        <ul class="nav nav-pills arrow-navtabs nav-secondary gap-2 flex-grow-1" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#personalDetails">
                                    <i class="ri-user-line me-1"></i> Profile &amp; Details
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#accessInfo">
                                    <i class="ri-shield-user-line me-1"></i> Access
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#activityTimeline">
                                    <i class="ri-history-line me-1"></i> Activity
                                </a>
                            </li>
                        </ul>
                        <div class="flex-shrink-0 ms-auto d-flex gap-2">
                            @if($canEdit)
                                <a href="{{ route('profile.settings', $user->id) }}" class="btn btn-primary">
                                    <i class="ri-edit-line me-1"></i> Edit Profile
                                </a>
                            @endif
                            @can('View user')
                                <a href="{{ route('users.index') }}" class="btn btn-secondary">
                                    <i class="bi bi-arrow-left me-1"></i> Back to Users
                                </a>
                            @endcan
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content">

                                <!-- Personal Details -->
                                <div class="tab-pane active" id="personalDetails" role="tabpanel">
                                    <div class="text-center mb-5">
                                        <div class="position-relative d-inline-block">
                                            <div class="avatar-xxl">
                                                @if($user->avatar_path_url)
                                                    <img src="{{ $user->avatar_path_url }}" alt="{{ $user->name }}" class="rounded-circle img-thumbnail">
                                                @else
                                                    <div class="avatar-title rounded-circle bg-light text-primary fs-1" style="width:150px;height:150px;line-height:150px;">{{ $user->initials }}</div>
                                                @endif
                                            </div>
                                        </div>
                                        <h4 class="mt-3 mb-1">{{ $user->name }}</h4>
                                        <p class="text-muted mb-1">{{ $user->job_title ?: $user->user_type_label }}</p>
                                        <p class="text-muted mb-0">{{ $user->email }}</p>
                                        <div class="mt-2">
                                            <span class="badge bg-light text-dark border me-1">{{ $user->user_type_label }}</span>
                                            @if($user->is_disabled)
                                                <span class="badge bg-danger">Sign-in disabled</span>
                                            @endif
                                        </div>
                                    </div>

                                    <div class="row g-3">
                                        <div class="col-12"><h5 class="mb-3 border-bottom pb-2">Personal Information</h5></div>
                                        <div class="col-md-6"><label class="form-label">Full Name</label><input type="text" class="form-control" value="{{ $user->name }}" readonly></div>
                                        <div class="col-md-6"><label class="form-label">Username</label><input type="text" class="form-control" value="{{ $user->username ?: '—' }}" readonly></div>
                                        <div class="col-md-6"><label class="form-label">Phone Number</label><input type="text" class="form-control" value="{{ $user->phone_number ?: '—' }}" readonly></div>
                                        <div class="col-md-6"><label class="form-label">Gender</label><input type="text" class="form-control" value="{{ $user->gender ? ucfirst($user->gender) : '—' }}" readonly></div>
                                        <div class="col-md-6"><label class="form-label">Date of Birth</label><input type="text" class="form-control" value="{{ $user->formatted_dob ?? '—' }}" readonly></div>
                                        <div class="col-md-6"><label class="form-label">Job Title</label><input type="text" class="form-control" value="{{ $user->job_title ?: '—' }}" readonly></div>
                                        <div class="col-12"><label class="form-label">Address</label><textarea class="form-control" rows="2" readonly>{{ $user->address ?: '—' }}</textarea></div>
                                        @if($user->bio)
                                            <div class="col-12"><label class="form-label">About</label><textarea class="form-control" rows="3" readonly>{{ $user->bio }}</textarea></div>
                                        @endif
                                    </div>
                                </div>

                                <!-- Access: roles and permissions -->
                                <div class="tab-pane" id="accessInfo" role="tabpanel">
                                    <h5 class="mb-3 border-bottom pb-2">Roles</h5>
                                    <div class="mb-4">
                                        @forelse($user->roles as $role)
                                            <span class="badge fs-6 me-1 mb-1" style="background: {{ $role->badge ?: '#405189' }};">{{ $role->name }}</span>
                                        @empty
                                            <p class="text-muted mb-0">No role assigned yet — this person can sign in but cannot open any module.</p>
                                        @endforelse
                                    </div>

                                    <h5 class="mb-3 border-bottom pb-2">What they can do</h5>
                                    @if($user->isSuperAdmin())
                                        <p class="text-muted">Super Administrator — every permission, including those added by future modules.</p>
                                    @else
                                        @php $grouped = $user->getAllPermissions()->groupBy(fn ($p) => $p->title ?: 'Other')->sortKeys(); @endphp
                                        @forelse($grouped as $group => $perms)
                                            <div class="mb-3">
                                                <div class="fw-semibold mb-1">{{ $group }}</div>
                                                @foreach($perms as $p)
                                                    <span class="badge bg-light text-dark border me-1 mb-1" title="{{ $p->description }}">{{ $p->name }}</span>
                                                @endforeach
                                            </div>
                                        @empty
                                            <p class="text-muted mb-0">No permissions.</p>
                                        @endforelse
                                    @endif
                                </div>

                                <!-- Activity Timeline -->
                                <div class="tab-pane" id="activityTimeline" role="tabpanel">
                                    <h5 class="mb-4">Account Activity</h5>
                                    <div class="timeline">
                                        <div class="timeline-item">
                                            <div class="timeline-date">{{ $user->created_at?->format('d M Y') }}</div>
                                            <div class="timeline-content">
                                                <h6>Account Created</h6>
                                                <p class="text-muted mb-0">The account was created on {{ $brand['name'] }}</p>
                                            </div>
                                        </div>
                                        <div class="timeline-item">
                                            <div class="timeline-date">{{ $user->last_login_at ? $user->last_login_at->format('d M Y, h:i A') : '—' }}</div>
                                            <div class="timeline-content">
                                                <h6>Last Sign-in</h6>
                                                <p class="text-muted mb-0">{{ $user->last_login_at ? 'From ' . ($user->last_login_ip ?: 'an unknown address') : 'Has not signed in yet' }}</p>
                                            </div>
                                        </div>
                                        @foreach($recentActivity as $a)
                                            <div class="timeline-item">
                                                <div class="timeline-date">{{ \Carbon\Carbon::parse($a->created_at)->format('d M Y, h:i A') }}</div>
                                                <div class="timeline-content">
                                                    <h6>{{ ucfirst(str_replace('_', ' ', $a->event)) }}</h6>
                                                    <p class="text-muted mb-0">{{ $a->description }}</p>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                    @can('View activity log')
                                        <a href="{{ route('activity.index', ['user' => $user->id]) }}" class="btn btn-soft-primary btn-sm"><i class="ri-history-line me-1"></i> Full activity log</a>
                                    @endcan
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding-left: 40px;
}
.timeline::before {
    content: '';
    position: absolute;
    left: 18px;
    top: 0;
    bottom: 0;
    width: 4px;
    background: #e9ecef;
}
.timeline-item {
    position: relative;
    margin-bottom: 30px;
}
.timeline-item::before {
    content: '';
    position: absolute;
    left: -24px;
    top: 8px;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #4e73df;
    border: 3px solid #fff;
    box-shadow: 0 0 0 4px #e3e8ff;
}
.timeline-date {
    font-size: 0.85rem;
    color: #6c757d;
    margin-bottom: 5px;
}
.timeline-content h6 {
    margin: 0 0 5px 0;
    color: #495057;
}
.avatar-xxl {
    width: 150px;
    height: 150px;
}
.avatar-xxl img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}
.border-bottom {
    border-bottom: 2px solid var(--bs-border-color) !important;
}
</style>
@endsection
