@extends('layouts.master')

@section('content')
<div class="main-content">
    <div class="page-content">
        <div class="container-fluid">

            <!-- Page Title -->
            <div class="row">
                <div class="col-12">
                    <div class="page-title-box d-sm-flex align-items-center justify-content-between">
                        <h4 class="mb-sm-0">{{ $isSelf ? 'My Profile Settings' : $user->name . ' — Profile Settings' }}</h4>
                        <div class="page-title-right">
                            <ol class="breadcrumb m-0">
                                @can('View user')
                                    <li class="breadcrumb-item"><a href="{{ route('users.index') }}">Users</a></li>
                                @endcan
                                <li class="breadcrumb-item"><a href="{{ route('users.overview', $user->id) }}">{{ $user->name }}</a></li>
                                <li class="breadcrumb-item active">Settings</li>
                            </ol>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Flash Messages -->
            @if(session('success'))
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="ri-check-line me-2"></i> {{ session('success') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if(session('error'))
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-2"></i> {{ session('error') }}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <i class="ri-error-warning-line me-2"></i>
                    <ul class="mb-0">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            @endif

            <div class="row">
                <div class="col-xxl-12">

                    <!-- Tabs Navigation -->
                    <div class="d-flex align-items-center flex-wrap gap-2 mb-4">
                        <ul class="nav nav-pills arrow-navtabs nav-secondary gap-2 flex-grow-1" role="tablist">
                            <li class="nav-item">
                                <a class="nav-link active" data-bs-toggle="tab" href="#personalDetails">
                                    <i class="ri-user-line me-1"></i> Personal Details
                                </a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" data-bs-toggle="tab" href="#security">
                                    <i class="ri-lock-line me-1"></i> Security
                                </a>
                            </li>
                        </ul>
                        <div class="flex-shrink-0 ms-auto">
                            <a href="{{ route('users.overview', $user->id) }}" class="btn btn-secondary">
                                <i class="bi bi-arrow-left me-1"></i> Back to Profile
                            </a>
                        </div>
                    </div>

                    <div class="card">
                        <div class="card-body">
                            <div class="tab-content">

                                <!-- Personal Details Tab -->
                                <div class="tab-pane active" id="personalDetails" role="tabpanel">
                                    <div class="text-center mb-5">
                                        <div class="position-relative d-inline-block">
                                            <div class="avatar-xxl">
                                                @if($user->avatar_path_url)
                                                    <img src="{{ $user->avatar_path_url }}"
                                                         alt="Profile"
                                                         class="rounded-circle img-thumbnail"
                                                         id="profilePreview"
                                                         style="width: 150px; height: 150px; object-fit: cover;">
                                                @else
                                                    <div class="avatar-title rounded-circle bg-light text-primary fs-1"
                                                         style="width: 150px; height: 150px; line-height: 150px;"
                                                         id="profilePreview">
                                                        {{ $user->initials }}
                                                    </div>
                                                @endif
                                            </div>

                                            <label for="avatar" class="position-absolute bottom-0 end-0 btn btn-sm btn-icon btn-primary rounded-circle" title="Change photo">
                                                <i class="ri-camera-line fs-16"></i>
                                                <input type="file" id="avatar" name="avatar" class="d-none" accept="image/*">
                                            </label>
                                        </div>
                                        <h4 class="mt-3 mb-1">{{ $user->name }}</h4>
                                        <p class="text-muted mb-0">{{ $user->email }}</p>
                                        <div class="mt-2">
                                            <span class="badge bg-light text-dark border me-1">{{ $user->user_type_label }}</span>
                                            @foreach($user->roles as $role)
                                                <span class="badge bg-info me-1">{{ $role->name }}</span>
                                            @endforeach
                                        </div>
                                    </div>

                                    <form action="{{ route('profile.update-info') }}" method="POST">
                                        @csrf
                                        <input type="hidden" name="id" value="{{ $user->id }}">
                                        <div class="row g-3">
                                            <div class="col-12"><h5 class="mb-3 border-bottom pb-2">Personal Information</h5></div>
                                            <div class="col-md-6">
                                                <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                                <input type="text" name="name" class="form-control" value="{{ old('name', $user->name) }}" required>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Username</label>
                                                <input type="text" name="username" class="form-control" value="{{ old('username', $user->username) }}" placeholder="Optional — can be used to sign in">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Phone Number</label>
                                                <input type="text" name="phone_number" class="form-control" value="{{ old('phone_number', $user->phone_number) }}" placeholder="+234…">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Job Title</label>
                                                <input type="text" name="job_title" class="form-control" value="{{ old('job_title', $user->job_title) }}">
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Gender</label>
                                                <select name="gender" class="form-control">
                                                    <option value="">Select</option>
                                                    <option value="male" @selected(old('gender', $user->gender) === 'male')>Male</option>
                                                    <option value="female" @selected(old('gender', $user->gender) === 'female')>Female</option>
                                                </select>
                                            </div>
                                            <div class="col-md-6">
                                                <label class="form-label">Date of Birth</label>
                                                <input type="date" name="date_of_birth" class="form-control" value="{{ old('date_of_birth', $user->date_of_birth?->format('Y-m-d')) }}">
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">Address</label>
                                                <textarea name="address" class="form-control" rows="2">{{ old('address', $user->address) }}</textarea>
                                            </div>
                                            <div class="col-12">
                                                <label class="form-label">About</label>
                                                <textarea name="bio" class="form-control" rows="3" placeholder="A short introduction your colleagues will see">{{ old('bio', $user->bio) }}</textarea>
                                            </div>
                                            <div class="col-12 text-end mt-4">
                                                <button type="reset" class="btn btn-light me-2">Reset</button>
                                                <button type="submit" class="btn btn-primary"><i class="ri-save-line me-1"></i> Save Changes</button>
                                            </div>
                                        </div>
                                    </form>
                                </div>

                                <!-- Security Tab -->
                                <div class="tab-pane" id="security" role="tabpanel">
                                    <div class="row">
                                        <div class="col-lg-6 mb-4">
                                            <div class="card border">
                                                <div class="card-header bg-light"><h5 class="card-title mb-0"><i class="ri-mail-line me-2"></i>Update Email</h5></div>
                                                <div class="card-body">
                                                    <form id="updateEmailForm">
                                                        @csrf
                                                        <input type="hidden" name="userid" value="{{ $user->id }}">
                                                        <div class="mb-3"><label class="form-label">Current Email</label><input type="email" class="form-control" value="{{ $user->email }}" readonly></div>
                                                        <div class="mb-3"><label class="form-label">New Email *</label><input type="email" name="emailaddress" class="form-control" required></div>
                                                        <div class="mb-3"><label class="form-label">Confirm New Email *</label><input type="email" name="emailaddress_confirmation" class="form-control" required></div>
                                                        <button type="submit" class="btn btn-primary"><span class="spinner-border spinner-border-sm d-none me-1"></span> Update Email</button>
                                                    </form>
                                                    <div id="emailMessage" class="mt-3"></div>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-lg-6 mb-4">
                                            <div class="card border">
                                                <div class="card-header bg-light"><h5 class="card-title mb-0"><i class="ri-lock-line me-2"></i>{{ $isSelf ? 'Change Password' : 'Reset Password' }}</h5></div>
                                                <div class="card-body">
                                                    <form id="updatePasswordForm">
                                                        @csrf
                                                        <input type="hidden" name="userid" value="{{ $user->id }}">
                                                        @if($isSelf && !$user->must_change_password)
                                                            <div class="mb-3"><label class="form-label">Current Password *</label><input type="password" name="current_password" class="form-control" autocomplete="current-password" required></div>
                                                        @endif
                                                        <div class="mb-3"><label class="form-label">New Password *</label><input type="password" name="password" class="form-control" autocomplete="new-password" required></div>
                                                        <div class="mb-3"><label class="form-label">Confirm Password *</label><input type="password" name="password_confirmation" class="form-control" autocomplete="new-password" required></div>
                                                        <div class="alert alert-info">
                                                            <i class="ri-information-line me-2"></i> At least 8 characters, with letters and numbers.
                                                            @unless($isSelf) {{ $user->first_name }} will be asked to choose a new password at their next sign-in. @endunless
                                                        </div>
                                                        <button type="submit" class="btn btn-primary"><span class="spinner-border spinner-border-sm d-none me-1"></span> Update Password</button>
                                                    </form>
                                                    <div id="passwordMessage" class="mt-3"></div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
// Session messages
@if(session('success'))
    Swal.fire({
        icon: 'success',
        title: 'Success',
        text: @json(session('success')),
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false
    });
@endif
@if(session('error'))
    Swal.fire({
        icon: 'error',
        title: 'Error',
        text: @json(session('error')),
        toast: true,
        position: 'top-end',
        timer: 3000,
        showConfirmButton: false
    });
@endif

// Avatar upload
document.getElementById('avatar')?.addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (!file) return;

    if (file.size > 5 * 1024 * 1024) {
        Swal.fire('Error', 'Image must be less than 5MB', 'error');
        this.value = '';
        return;
    }

    if (!file.type.match('image.*')) {
        Swal.fire('Error', 'Please select an image file', 'error');
        this.value = '';
        return;
    }

    const reader = new FileReader();
    reader.onload = function(e) {
        const preview = document.getElementById('profilePreview');
        const fallback = document.getElementById('profileFallback') || null;

        if (preview.tagName === 'IMG') {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            if (fallback) fallback.classList.add('d-none');
        } else {
            const img = document.createElement('img');
            img.src = e.target.result;
            img.alt = 'Profile';
            img.className = 'rounded-circle img-thumbnail';
            img.id = 'profilePreview';
            img.style.cssText = 'width: 150px; height: 150px; object-fit: cover;';
            img.onerror = function() {
                this.onerror = null;
                this.src = '{{ $user->avatar_url }}';
            };
            preview.parentNode.replaceChild(img, preview);
        }
    };
    reader.readAsDataURL(file);

    const fd = new FormData();
    fd.append('avatar', file);
    fd.append('id', '{{ $user->id }}');
    fd.append('_token', '{{ csrf_token() }}');

    Swal.fire({
        title: 'Uploading...',
        html: 'Please wait while we upload your profile picture',
        allowOutsideClick: false,
        didOpen: () => Swal.showLoading()
    });

    fetch('{{ route("profile.update-avatar") }}', {
        method: 'POST',
        body: fd,
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        Swal.close();
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });

            if (data.avatar_url) {
                document.getElementById('profilePreview').src = data.avatar_url + '?t=' + Date.now();
            }
        } else {
            Swal.fire('Error!', data.message || 'Failed to upload avatar', 'error');
        }
    })
    .catch(error => {
        Swal.close();
        Swal.fire('Error!', 'Network error occurred. Please try again.', 'error');
    });
});

// Email Update Form
document.getElementById('updateEmailForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const fd = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');
    const spinner = btn.querySelector('.spinner-border');
    const msgDiv = document.getElementById('emailMessage');

    btn.disabled = true;
    if (spinner) spinner.classList.remove('d-none');

    fetch('{{ route("profile.update-email") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
            msgDiv.innerHTML = `<div class="alert alert-success alert-dismissible fade show mt-3">
                <i class="ri-check-line me-2"></i>${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            form.reset();
        } else {
            Swal.fire('Error!', data.message || 'Failed to update email', 'error');
            msgDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show mt-3">
                <i class="ri-error-warning-line me-2"></i>${data.message || 'An error occurred'}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        }
    })
    .catch(error => {
        Swal.fire('Error!', 'Network error. Please try again.', 'error');
        msgDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show mt-3">
            <i class="ri-error-warning-line me-2"></i>Request failed. Please try again.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    })
    .finally(() => {
        btn.disabled = false;
        if (spinner) spinner.classList.add('d-none');
    });
});

// Password Update Form
document.getElementById('updatePasswordForm')?.addEventListener('submit', function(e) {
    e.preventDefault();

    const form = this;
    const fd = new FormData(form);
    const btn = form.querySelector('button[type="submit"]');
    const spinner = btn.querySelector('.spinner-border');
    const msgDiv = document.getElementById('passwordMessage');

    btn.disabled = true;
    if (spinner) spinner.classList.remove('d-none');

    fetch('{{ route("profile.update-password") }}', {
        method: 'POST',
        headers: {
            'X-CSRF-TOKEN': '{{ csrf_token() }}',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: fd
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            Swal.fire({
                icon: 'success',
                title: 'Success!',
                text: data.message,
                timer: 2000,
                showConfirmButton: false
            });
            msgDiv.innerHTML = `<div class="alert alert-success alert-dismissible fade show mt-3">
                <i class="ri-check-line me-2"></i>${data.message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
            form.reset();
        } else {
            Swal.fire('Error!', data.message || 'Failed to update password', 'error');
            msgDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show mt-3">
                <i class="ri-error-warning-line me-2"></i>${data.message || 'An error occurred'}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>`;
        }
    })
    .catch(error => {
        Swal.fire('Error!', 'Network error. Please try again.', 'error');
        msgDiv.innerHTML = `<div class="alert alert-danger alert-dismissible fade show mt-3">
            <i class="ri-error-warning-line me-2"></i>Request failed. Please try again.
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>`;
    })
    .finally(() => {
        btn.disabled = false;
        if (spinner) spinner.classList.add('d-none');
    });
});

// Tab persistence (remember last active tab)
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('a[data-bs-toggle="tab"]').forEach(tab => {
        tab.addEventListener('shown.bs.tab', e => {
            localStorage.setItem('activeProfileTab', e.target.getAttribute('href'));
        });
    });

    const saved = localStorage.getItem('activeProfileTab');
    if (saved) {
        const tab = document.querySelector(`a[href="${saved}"]`);
        if (tab) {
            const bsTab = new bootstrap.Tab(tab);
            bsTab.show();
        }
    }
});
</script>
@endsection
