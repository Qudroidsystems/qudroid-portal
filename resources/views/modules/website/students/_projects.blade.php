{{-- Projects list + add/edit form. Needs $p, $project (being edited or null), $courses, $routes ('store','update','destroy' => fn(?$project) url), $editUrl (fn($project)). --}}
@php $editing = $project !== null; @endphp
<x-cb.card title="Projects" icon="ri-code-box-line" :count="$p->exists ? $p->projects->count() : 0" class="mt-3">
    @if($p->exists && $p->projects->isNotEmpty())
        <div class="sp-projects mb-3">
            @foreach($p->projects as $pr)
                <div class="sp-project {{ $editing && $project->id === $pr->id ? 'is-editing' : '' }}">
                    <div class="sp-shot">@if($pr->imageUrl())<img src="{{ $pr->imageUrl() }}" alt="">@else<i class="ri-code-box-line"></i>@endif</div>
                    <div class="flex-grow-1 min-w-0">
                        <div class="fw-semibold text-truncate">{{ $pr->title }}</div>
                        <div class="small text-muted text-truncate">{{ collect([$pr->course?->title, $pr->url ? parse_url($pr->url, PHP_URL_HOST) : null])->filter()->implode(' · ') ?: 'No link yet' }}</div>
                    </div>
                    <a href="{{ $editUrl($pr) }}" class="btn btn-sm btn-soft-primary">Edit</a>
                    <form method="POST" action="{{ $routes['destroy']($pr) }}" onsubmit="return confirm('Remove this project?')">@csrf @method('DELETE')<button class="btn btn-sm btn-ghost-danger" title="Remove"><i class="ri-delete-bin-line"></i></button></form>
                </div>
            @endforeach
        </div>
    @endif

    <form method="POST" action="{{ $editing ? $routes['update']($project) : $routes['store'](null) }}" enctype="multipart/form-data" class="sp-project-form" id="projectForm">
        @csrf @if($editing) @method('PUT') @endif
        <h6 class="mb-3">{{ $editing ? 'Edit "' . $project->title . '"' : 'Add a project' }}</h6>
        <div class="row g-3">
            <div class="col-md-7"><label class="form-label">Title</label><input name="title" class="form-control" required maxlength="150" value="{{ old('title', $project?->title) }}" placeholder="e.g. Recipe finder app"></div>
            <div class="col-md-5"><label class="form-label">Built on the course</label>
                <select name="course_id" class="form-select"><option value="">—</option>@foreach($courses as $c)<option value="{{ $c->id }}" @selected(old('course_id', $project?->course_id) == $c->id)>{{ $c->title }}</option>@endforeach</select></div>
            <div class="col-12"><label class="form-label">What it does</label><textarea name="summary" class="form-control" rows="2" maxlength="500">{{ old('summary', $project?->summary) }}</textarea></div>
            <div class="col-md-6"><label class="form-label">Live link</label><input name="url" class="form-control" maxlength="500" value="{{ old('url', $project?->url) }}" placeholder="https://myapp.netlify.app"></div>
            <div class="col-md-6"><label class="form-label">Code link</label><input name="repo_url" class="form-control" maxlength="500" value="{{ old('repo_url', $project?->repo_url) }}" placeholder="https://github.com/you/project"></div>
            <div class="col-md-6"><label class="form-label">Tools used</label><input name="tags" class="form-control" maxlength="300" value="{{ old('tags', implode(', ', $project?->tags ?? [])) }}" placeholder="React, Firebase"></div>
            <div class="col-md-4"><label class="form-label">Screenshot</label><input type="file" name="image_file" accept="image/*" class="form-control">
                @if($project?->image)<div class="form-check small mt-1"><input class="form-check-input" type="checkbox" name="remove_image" value="1" id="rmImg"><label class="form-check-label" for="rmImg">Remove screenshot</label></div>@endif</div>
            <div class="col-md-2"><label class="form-label">Order</label><input name="position" type="number" min="0" max="999" class="form-control" value="{{ old('position', $project?->position ?? 0) }}"></div>
        </div>
        <div class="d-flex gap-2 mt-3">
            <button class="btn btn-primary">{{ $editing ? 'Save project' : 'Add project' }}</button>
            @if($editing)<a href="{{ $editUrl(null) }}" class="btn btn-light">Cancel</a>@endif
        </div>
    </form>
</x-cb.card>
