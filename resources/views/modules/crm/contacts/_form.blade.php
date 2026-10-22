{{-- Contact fields. Needs $contact (may be new), $companies; optional $fixedCompany. --}}
<div class="row g-2">
    <div class="col-md-6"><label class="form-label small">Name</label><input name="name" class="form-control form-control-sm" value="{{ $contact->name }}" maxlength="150" required></div>
    <div class="col-md-6"><label class="form-label small">Job title</label><input name="job_title" class="form-control form-control-sm" value="{{ $contact->job_title }}" maxlength="100"></div>
    <div class="col-md-6"><label class="form-label small">Email</label><input type="email" name="email" class="form-control form-control-sm" value="{{ $contact->email }}" maxlength="150"></div>
    <div class="col-md-6"><label class="form-label small">Phone</label><input name="phone" class="form-control form-control-sm" value="{{ $contact->phone }}" maxlength="40"></div>
    @if(isset($fixedCompany))
        <input type="hidden" name="company_id" value="{{ $fixedCompany->id }}">
    @else
        <div class="col-12"><label class="form-label small">Company</label>
            <select name="company_id" class="form-select form-select-sm"><option value="">None</option>@foreach($companies as $c)<option value="{{ $c->id }}" @selected($contact->company_id === $c->id)>{{ $c->name }}</option>@endforeach</select></div>
    @endif
    <div class="col-12"><div class="form-check"><input type="checkbox" name="is_primary" value="1" class="form-check-input" id="primary-{{ $contact->id ?? 'new' }}" @checked($contact->is_primary)><label class="form-check-label small" for="primary-{{ $contact->id ?? 'new' }}">Main contact at the company</label></div></div>
</div>
