{{-- Profile fields, shared by the student's own page and the staff edit page. Needs $p. --}}
@php $photo = $p->photoUrl(); @endphp
<div class="row g-3">
    <div class="col-md-3 text-center">
        <div class="sp-photo mx-auto mb-2">@if($photo)<img src="{{ $photo }}" alt="">@else<span>{{ $p->initials() }}</span>@endif</div>
        <label class="btn btn-sm btn-soft-primary mb-1"><i class="ri-camera-line me-1"></i>{{ $photo ? 'Change photo' : 'Add a photo' }}<input type="file" name="photo_file" accept="image/*" class="d-none" onchange="this.closest('label').classList.replace('btn-soft-primary','btn-success')"></label>
        @if($p->photo)<div class="form-check d-inline-block small"><input class="form-check-input" type="checkbox" name="remove_photo" value="1" id="rmPhoto"><label class="form-check-label" for="rmPhoto">Remove photo</label></div>@endif
        <div class="form-text">A clear, friendly head-and-shoulders photo works best.</div>
    </div>
    <div class="col-md-9">
        <div class="row g-3">
            <div class="col-md-8"><label class="form-label">Headline</label><input name="headline" class="form-control" maxlength="120" value="{{ old('headline', $p->headline) }}" placeholder="e.g. Frontend developer who loves clean design"></div>
            <div class="col-md-4"><label class="form-label">Location</label><input name="location" class="form-control" maxlength="80" value="{{ old('location', $p->location) }}" placeholder="e.g. Lagos"></div>
            <div class="col-12"><label class="form-label">About</label><textarea name="bio" class="form-control" rows="4" maxlength="1500" placeholder="A few sentences: what you build, what you enjoy, what you are looking for.">{{ old('bio', $p->bio) }}</textarea></div>
            <div class="col-12"><label class="form-label">Skills</label><input name="skills" class="form-control" maxlength="400" value="{{ old('skills', implode(', ', $p->skills ?? [])) }}" placeholder="HTML, CSS, JavaScript, Laravel"><div class="form-text">Separate with commas. Up to 15.</div></div>
            @foreach(\App\Models\Website\StudentProfile::LINKS as $k => [$label, $icon])
                <div class="col-md-6"><label class="form-label small"><i class="{{ $icon }}"></i> {{ $label }}</label><input name="links[{{ $k }}]" class="form-control form-control-sm" maxlength="300" value="{{ old('links.' . $k, $p->links[$k] ?? '') }}" placeholder="https://"></div>
            @endforeach
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" name="open_to_work" value="1" id="otw" @checked(old('open_to_work', $p->open_to_work))><label class="form-check-label" for="otw">I am open to work (shows an "Open to work" badge)</label></div></div>
        </div>
    </div>
</div>
