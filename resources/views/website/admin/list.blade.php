@extends('layouts.master')

@php
    $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p);
    $image = collect($t['fields'])->first(fn ($f) => $f[2] === 'image');
    $titleField = collect($t['fields'])->first(fn ($f) => in_array($f[2], ['text']))[0];
@endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$t['title']" icon="ri-list-check-2" :subtitle="$t['about']">
        <x-slot:actions><a class="cb-hero-btn" href="{{ route('website.admin.create', $t['key']) }}{{ $t['key'] === 'posts' && request('kind') ? '?kind=' . request('kind') : '' }}"><i class="ri-add-line me-1"></i>Add new</a></x-slot:actions>
    </x-cb.hero>
    @include('website.admin._nav')

    @if($t['key'] === 'people')
        <x-cb.card title="Add from staff records" icon="ri-user-search-line">
            <form method="POST" action="{{ route('website.admin.people.import') }}" class="row g-2 align-items-end">@csrf
                <div class="col-md-7"><label class="form-label">Staff</label><select name="user_ids[]" id="staffPick" class="form-select" multiple size="5"></select><div class="form-text">Hold Ctrl / Cmd to pick several. Names, titles and photos are copied from the staff records.</div></div>
                <div class="col-md-3"><label class="form-label">Group</label><select name="group" class="form-select"><option value="staff">Teachers &amp; staff</option><option value="leadership">School leadership</option><option value="board">Board &amp; governance</option></select></div>
                <div class="col-md-2"><button class="btn btn-primary w-100">Add</button></div>
            </form>
            <script>
                fetch('{{ route('website.admin.people.staff') }}', { headers: { Accept: 'application/json' } }).then(r => r.json()).then(rows => {
                    const s = document.getElementById('staffPick');
                    rows.forEach(r => { const o = document.createElement('option'); o.value = r.id; o.textContent = r.name + (r.job_title || r.position ? ' — ' + (r.job_title || r.position) : ''); s.appendChild(o); });
                    if (!rows.length) s.outerHTML = '<p class="text-muted small mb-0">Every staff member is already on the page (or no staff records found).</p>';
                });
            </script>
        </x-cb.card>
    @endif

    <x-cb.card :title="$t['title']" icon="ri-file-list-3-line" :count="$rows->total()" :flush="true">
        <x-slot:tools>
            @if($t['key'] === 'posts')
                @foreach(['' => 'All', 'news' => 'News', 'event' => 'Events', 'story' => 'Stories'] as $k => $l)
                    <a class="btn btn-sm {{ request('kind', '') === $k ? 'btn-primary' : 'btn-light' }}" href="{{ route('website.admin.list', ['posts', 'kind' => $k ?: null]) }}">{{ $l }}</a>
                @endforeach
            @endif
            <form class="d-flex gap-1"><input name="q" value="{{ request('q') }}" class="form-control form-control-sm" placeholder="Search"><button class="btn btn-sm btn-light"><i class="ri-search-line"></i></button></form>
        </x-slot:tools>
        <div class="table-responsive">
            <table class="table align-middle mb-0">
                <thead class="table-light"><tr>
                    @if($image)<th style="width:80px"></th>@endif
                    <th>{{ collect($t['fields'])->firstWhere(0, $titleField)[1] }}</th>
                    <th>Details</th><th>Status</th><th class="text-end">Actions</th>
                </tr></thead>
                <tbody>
                @forelse($rows as $r)
                    <tr>
                        @if($image)<td>@if($r->{$image[0]})<img src="{{ $F($r->{$image[0]}) }}" style="{{ $t['key'] === 'appscreens' ? 'width:34px;height:70px;object-position:top' : 'width:64px;height:44px' }};object-fit:cover;border-radius:8px">@else<span class="d-inline-block bg-light rounded" style="width:64px;height:44px"></span>@endif</td>@endif
                        <td><a href="{{ route('website.admin.edit', [$t['key'], $r->id]) }}" class="fw-semibold">{{ \Illuminate\Support\Str::limit($r->{$titleField}, 70) }}</a>
                            @if(isset($r->slug) && in_array($t['key'], ['pages', 'posts']))<div class="small text-muted">/{{ $t['key'] === 'pages' ? 'pages' : 'news' }}/{{ $r->slug }}</div>@endif</td>
                        <td class="small text-muted">
                            @switch($t['key'])
                                @case('posts') <span class="badge bg-light text-dark text-capitalize">{{ $r->type }}</span> {{ $r->type === 'event' && $r->event_date ? $r->event_date->format('d M Y') : optional($r->published_at)->format('d M Y') }} @if($r->featured)<span class="badge bg-warning-subtle text-warning">Featured</span>@endif @break
                                @case('pages') Menu: {{ $r->menu ?: '—' }} @break
                                @case('people') {{ $r->role }} · {{ ucfirst($r->group) }} @break
                                @default {{ \Illuminate\Support\Str::limit($r->subtitle ?? $r->body ?? '', 60) }}
                            @endswitch
                            @isset($r->sort)<span class="ms-1">· #{{ $r->sort }}</span>@endisset
                        </td>
                        <td>@if($r->published)<span class="badge bg-success-subtle text-success">Live</span>@else<span class="badge bg-secondary-subtle text-secondary">Hidden</span>@endif</td>
                        <td class="text-end text-nowrap">
                            @if($t['key'] === 'pages')<a class="btn btn-sm btn-light" target="_blank" href="{{ route('site.page', $r->slug) }}"><i class="ri-external-link-line"></i></a>@endif
                            @if($t['key'] === 'posts')<a class="btn btn-sm btn-light" target="_blank" href="{{ route('site.post', $r->slug) }}"><i class="ri-external-link-line"></i></a>@endif
                            <a class="btn btn-sm btn-light" href="{{ route('website.admin.edit', [$t['key'], $r->id]) }}"><i class="ri-edit-line"></i></a>
                            <form method="POST" action="{{ route('website.admin.destroy', [$t['key'], $r->id]) }}" class="d-inline" onsubmit="return confirm('Delete this item?')">@csrf @method('DELETE')<button class="btn btn-sm btn-light text-danger"><i class="ri-delete-bin-line"></i></button></form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="text-center text-muted py-5">Nothing here yet. <a href="{{ route('website.admin.create', $t['key']) }}">Add the first one</a>.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        @if($rows->hasPages())<div class="p-3">{{ $rows->links() }}</div>@endif
    </x-cb.card>
</div></div></div>
@endsection
