@extends('layouts.master')

@php
    $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p);
    $icons = ['ri-medal-line', 'ri-shield-check-line', 'ri-compass-3-line', 'ri-lightbulb-flash-line', 'ri-hand-heart-line', 'ri-sun-line', 'ri-book-open-line', 'ri-flask-line', 'ri-football-line', 'ri-music-2-line', 'ri-computer-line', 'ri-global-line', 'ri-heart-pulse-line', 'ri-team-line', 'ri-trophy-line', 'ri-plant-line', 'ri-bus-line', 'ri-home-smile-line', 'ri-star-line', 'ri-graduation-cap-line', 'ri-palette-line', 'ri-earth-line', 'ri-user-heart-line', 'ri-award-line'];
    $val = fn ($name) => old($name, $row->{$name} instanceof \Carbon\Carbon ? $row->{$name}->format('Y-m-d') : $row->{$name});
@endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="($row->exists ? 'Edit' : 'New') . ' — ' . $t['title']" icon="ri-edit-2-line" :back="route('website.admin.list', $t['key'])" back-label="{{ $t['title'] }}" />
    @include('website.admin._nav')

    <form method="POST" enctype="multipart/form-data" action="{{ $row->exists ? route('website.admin.update', [$t['key'], $row->id]) : route('website.admin.store', $t['key']) }}">@csrf
        <div class="row g-3">
            <div class="col-xl-8">
                <x-cb.card title="Content" icon="ri-file-text-line">
                    @foreach($t['fields'] as $f)
                        @php [$name, $label, $kind] = $f; $opt = $f[3] ?? []; @endphp
                        @continue(in_array($kind, ['check', 'number', 'image', 'select', 'date']))
                        <div class="mb-3">
                            <label class="form-label">{{ $label }}@if(!empty($opt['required'])) <span class="text-danger">*</span>@endif</label>
                            @if($kind === 'textarea')
                                <textarea name="{{ $name }}" class="form-control" rows="4" @required(!empty($opt['required']))>{{ $val($name) }}</textarea>
                            @elseif($kind === 'html')
                                @include('website.admin._editor', ['name' => $name, 'value' => $val($name), 'height' => 360])
                            @elseif($kind === 'icon')
                                <input type="hidden" name="{{ $name }}" id="iconVal" value="{{ $val($name) }}">
                                <div class="d-flex flex-wrap gap-2">
                                    @foreach($icons as $ic)
                                        <button type="button" class="btn btn-light icon-pick {{ $val($name) === $ic ? 'border-primary text-primary' : '' }}" data-icon="{{ $ic }}" style="width:46px;height:46px;font-size:1.3rem"><i class="{{ $ic }}"></i></button>
                                    @endforeach
                                </div>
                                <script>document.querySelectorAll('.icon-pick').forEach(b => b.onclick = () => { document.getElementById('iconVal').value = b.dataset.icon; document.querySelectorAll('.icon-pick').forEach(x => x.classList.remove('border-primary', 'text-primary')); b.classList.add('border-primary', 'text-primary'); });</script>
                            @else
                                <input name="{{ $name }}" class="form-control" value="{{ $val($name) }}" @required(!empty($opt['required']))>
                            @endif
                            @if(!empty($opt['hint']))<div class="form-text">{{ $opt['hint'] }}</div>@endif
                        </div>
                    @endforeach
                </x-cb.card>
            </div>
            <div class="col-xl-4">
                <x-cb.card title="Options" icon="ri-equalizer-line">
                    @foreach($t['fields'] as $f)
                        @php [$name, $label, $kind] = $f; $opt = $f[3] ?? []; @endphp
                        @if($kind === 'image')
                            <div class="mb-3">
                                <label class="form-label">{{ $label }}</label>
                                @if($row->{$name})<img src="{{ $F($row->{$name}) }}" class="d-block mb-2 rounded-3" style="max-width:100%;max-height:170px;object-fit:cover">@endif
                                <input type="file" name="{{ $name }}" accept="image/*" class="form-control">
                                @if($row->{$name})<div class="form-check mt-1"><input class="form-check-input" type="checkbox" name="remove_{{ $name }}" value="1" id="rm_{{ $name }}"><label class="form-check-label small" for="rm_{{ $name }}">Remove</label></div>@endif
                                <div class="form-text">JPG or PNG, up to 8 MB — resized automatically.</div>
                            </div>
                        @elseif($kind === 'select')
                            <div class="mb-3"><label class="form-label">{{ $label }}</label>
                                <select name="{{ $name }}" class="form-select">@foreach($opt['options'] as $k => $l)<option value="{{ $k }}" @selected($val($name) === $k)>{{ $l }}</option>@endforeach</select></div>
                        @elseif($kind === 'date')
                            <div class="mb-3"><label class="form-label">{{ $label }}</label><input type="date" name="{{ $name }}" class="form-control" value="{{ $val($name) }}"></div>
                        @elseif($kind === 'number')
                            <div class="mb-3"><label class="form-label">{{ $label }}</label><input type="number" min="0" name="{{ $name }}" class="form-control" value="{{ $val($name) ?? 0 }}"><div class="form-text">Smaller numbers come first.</div></div>
                        @elseif($kind === 'check')
                            <div class="form-check form-switch mb-2"><input class="form-check-input" type="checkbox" role="switch" name="{{ $name }}" value="1" id="ck_{{ $name }}" @checked(old($name, $row->{$name}))><label class="form-check-label" for="ck_{{ $name }}">{{ $label }}</label></div>
                        @endif
                    @endforeach
                </x-cb.card>
                <button class="btn btn-primary w-100"><i class="ri-save-3-line me-1"></i>Save</button>
                @if($row->exists && in_array($t['key'], ['pages', 'posts']))
                    <a class="btn btn-light w-100 mt-2" target="_blank" href="{{ $t['key'] === 'pages' ? route('site.page', $row->slug) : route('site.post', $row->slug) }}"><i class="ri-external-link-line me-1"></i>View on the website</a>
                @endif
            </div>
        </div>
    </form>
</div></div></div>
@endsection
