{{-- Validation errors box for the auth pages. --}}
@if ($errors->any())
    <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert" style="border-left: 4px solid #ef4444;">
        <div class="d-flex align-items-center">
            <i class="ri-error-warning-line me-2" style="font-size: 18px;"></i>
            <div>
                <strong>Please fix the following errors:</strong>
                <ul class="mb-0 mt-1 ps-3 small">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
    </div>
@endif
