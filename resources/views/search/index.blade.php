@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Search" icon="ri-search-line" subtitle="People, students, courses, projects, tasks, clients, deals, money, staff, stock, meetings, files and messages: everything you're allowed to see." />

    <form method="GET" action="{{ route('search') }}" class="mb-3" role="search">
        <div class="input-group input-group-lg">
            <span class="input-group-text"><i class="ri-search-line"></i></span>
            <input type="search" name="q" value="{{ $q }}" class="form-control" placeholder="Search everything…" autofocus minlength="2" maxlength="100" aria-label="Search">
            <button class="btn btn-primary">Search</button>
        </div>
        <div class="form-text">Tip: press <kbd>Ctrl</kbd>+<kbd>K</kbd> (or <kbd>⌘</kbd>+<kbd>K</kbd>) on any page. Type a task key like <code>WEB-14</code> to jump to it.</div>
    </form>

    @if(mb_strlen($q) < 2)
        <div class="text-muted">Type at least two letters.</div>
    @elseif(!$count)
        <div class="empty-state py-5"><i class="ri-search-eye-line"></i><h6>Nothing found for “{{ $q }}”</h6><p>Try fewer words or a different spelling.</p></div>
    @else
        <div class="small text-muted mb-2">{{ $count }} {{ \Illuminate\Support\Str::plural('result', $count) }} for “{{ $q }}”</div>
        <div class="row g-3">
            @foreach($groups as $category => $rows)
                <div class="col-lg-6">
                    <x-cb.card :title="$category" :icon="'ri-arrow-right-s-line'" :count="$rows->count()" :flush="true">
                        <div class="list-group list-group-flush">
                            @foreach($rows as $r)
                                <a href="{{ $r['url'] }}" class="list-group-item list-group-item-action d-flex gap-3 align-items-center">
                                    <i class="mdi {{ $r['icon'] }} fs-4" style="color: {{ \App\Services\Search\GlobalSearch::CATEGORIES[$category][1] ?? '#405189' }}"></i>
                                    <span class="flex-grow-1 text-truncate"><span class="d-block fw-semibold text-truncate">{{ $r['title'] }}</span><span class="small text-muted text-truncate d-block">{{ $r['subtitle'] }}</span></span>
                                </a>
                            @endforeach
                        </div>
                    </x-cb.card>
                </div>
            @endforeach
        </div>
    @endif
</div></div></div>
@endsection
