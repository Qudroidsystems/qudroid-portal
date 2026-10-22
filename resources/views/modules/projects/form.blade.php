@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">

    @php $editing = $project->exists; @endphp
    <x-cb.hero :title="$editing ? 'Edit ' . $project->name : 'New project'" icon="ri-layout-column-line" :back="$editing ? route('projects.show', $project) : route('projects.index')" :back-label="$editing ? $project->name : 'Projects'" />

    @include('partials.flash')

    <div class="row"><div class="col-xl-9">
        <x-cb.card>
            <form method="POST" action="{{ $editing ? route('projects.update', $project) : route('projects.store') }}">
                @csrf
                @if($editing) @method('PUT') @endif
                @if($deal ?? null)
                    <input type="hidden" name="deal_id" value="{{ $deal->id }}">
                    <div class="cb-banner info"><i class="ri-trophy-line"></i><div>Starting from the won deal <strong>{{ $deal->title }}</strong>. The deal will link to this project.</div></div>
                @endif
                <div class="row g-3">
                    <div class="col-md-8"><label class="form-label">Name</label><input name="name" class="form-control" value="{{ old('name', $project->name) }}" maxlength="150" required></div>
                    <div class="col-md-4"><label class="form-label">Code</label>
                        @if($editing)
                            <input class="form-control" value="{{ $project->code }}" disabled>
                        @else
                            <input name="code" class="form-control text-uppercase" value="{{ old('code') }}" maxlength="10" required placeholder="WEB" pattern="[A-Za-z][A-Za-z0-9]{1,9}">
                            <div class="form-text">Short key for task numbers (WEB-1, WEB-2). Cannot be changed later.</div>
                        @endif
                    </div>
                    <div class="col-12"><label class="form-label">Description</label><textarea name="description" rows="4" class="form-control" maxlength="5000">{{ old('description', $project->description) }}</textarea></div>
                    <div class="col-md-6"><label class="form-label">Client name</label><input name="client_name" class="form-control" value="{{ old('client_name', $project->client_name) }}" maxlength="150" placeholder="Leave empty for an internal project"></div>
                    @if($companies->isNotEmpty())
                        <div class="col-md-6"><label class="form-label">CRM company <span class="text-muted">(optional)</span></label>
                            <select name="company_id" class="form-select">
                                <option value="">None</option>
                                @foreach($companies as $c)<option value="{{ $c->id }}" @selected((int) old('company_id', $project->company_id) === $c->id)>{{ $c->name }}</option>@endforeach
                            </select>
                        </div>
                    @endif
                    <div class="col-md-6"><label class="form-label">Client portal account <span class="text-muted">(optional)</span></label>
                        <select name="client_id" class="form-select">
                            <option value="">None</option>
                            @foreach($clients as $c)<option value="{{ $c->id }}" @selected((int) old('client_id', $project->client_id) === $c->id)>{{ $c->name }}</option>@endforeach
                        </select>
                        <div class="form-text">They can follow the project and see tasks you share with them.</div>
                    </div>
                    <div class="col-md-6"><label class="form-label">Manager</label>
                        <select name="manager_id" class="form-select">
                            <option value="">{{ $editing ? 'Unchanged' : 'Me' }}</option>
                            @foreach($staff as $s)<option value="{{ $s->id }}" @selected((int) old('manager_id', $project->manager_id) === $s->id)>{{ $s->name }}</option>@endforeach
                        </select>
                    </div>
                    <div class="col-md-3"><label class="form-label">Priority</label>
                        <select name="priority" class="form-select">
                            @foreach(\App\Models\Projects\Project::PRIORITIES as $k => $v)<option value="{{ $k }}" @selected(old('priority', $project->priority) === $k)>{{ $v }}</option>@endforeach
                        </select>
                    </div>
                    @unless($editing)
                        <div class="col-md-3"><label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach(['planning', 'active'] as $k)<option value="{{ $k }}" @selected(old('status', $project->status ?? 'planning') === $k)>{{ \App\Models\Projects\Project::STATUSES[$k] }}</option>@endforeach
                            </select>
                        </div>
                    @endunless
                    <div class="col-md-3"><label class="form-label">Starts</label><input type="date" name="starts_on" class="form-control" value="{{ old('starts_on', $project->starts_on?->toDateString()) }}"></div>
                    <div class="col-md-3"><label class="form-label">Due</label><input type="date" name="due_on" class="form-control" value="{{ old('due_on', $project->due_on?->toDateString()) }}"></div>
                    <div class="col-md-4"><label class="form-label">Budget ({{ $project->currency ?? config('qudroid.academy.currency', 'NGN') }})</label><input type="number" step="0.01" min="0" name="budget" class="form-control" value="{{ old('budget', $project->budget) }}"></div>
                    <div class="col-md-2"><label class="form-label">Colour</label><input type="color" name="color" class="form-control form-control-color w-100" value="{{ old('color', $project->color ?? '#0ea5e9') }}"></div>
                </div>
                <div class="mt-4"><button class="btn btn-primary">{{ $editing ? 'Save' : 'Create project' }}</button></div>
            </form>
        </x-cb.card>
    </div></div>

</div></div></div>
@endsection
