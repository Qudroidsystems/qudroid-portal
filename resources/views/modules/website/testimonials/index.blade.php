@extends('layouts.master')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="Testimonials" icon="ri-chat-quote-line" subtitle="What students and clients say. Students write theirs in My academy; clients use a link you send them. Both wait for you to publish them." :back="route('website.index')" back-label="Website">
        <x-slot:actions>
            <a class="cb-hero-btn" href="{{ route('website.testimonials.create', ['kind' => $kind ?? 'student']) }}"><i class="ri-add-line me-1"></i>Add one</a>
            <a class="cb-hero-btn" href="{{ route('site.testimonials') }}" target="_blank"><i class="ri-external-link-line me-1"></i>View page</a>
        </x-slot:actions>
    </x-cb.hero>
    @include('partials.flash')

    <div class="row g-3">
        <div class="col-xl-9">
            <x-cb.card title="All testimonials" icon="ri-chat-quote-line" :count="$items->count()" :flush="true">
                <x-slot:tools>
                    <div class="btn-group btn-group-sm">
                        @foreach([null => 'Everyone', 'student' => 'Students', 'client' => 'Clients'] as $k => $label)
                            <a href="{{ route('website.testimonials.index', array_filter(['kind' => $k ?: null, 'status' => $status])) }}" class="btn btn-{{ ($kind ?? '') === $k ? 'primary' : 'light' }}">{{ $label }}</a>
                        @endforeach
                    </div>
                    <a href="{{ route('website.testimonials.index', array_filter(['kind' => $kind, 'status' => $status === 'pending' ? null : 'pending'])) }}" class="btn btn-sm btn-{{ $status === 'pending' ? 'warning' : 'light' }}">Waiting @if($pending)<span class="badge bg-warning ms-1">{{ $pending }}</span>@endif</a>
                </x-slot:tools>
                @forelse($items as $t)
                    <div class="d-flex gap-3 align-items-start border-top p-3">
                        <div class="sp-photo" style="width:44px;height:44px;font-size:15px;{{ $t->kind === 'client' ? 'border-radius:10px' : '' }}">@if($t->photoUrl())<img src="{{ $t->photoUrl() }}" alt="">@else<span>{{ $t->initials() }}</span>@endif</div>
                        <div class="flex-grow-1 min-w-0">
                            <a href="{{ route('website.testimonials.edit', $t) }}" class="fw-semibold">{{ $t->name }}</a>
                            <span class="badge bg-light text-muted ms-1">{{ \App\Models\Website\Testimonial::KINDS[$t->kind] }}</span>
                            @if($t->featured)<span class="badge bg-warning-subtle text-warning ms-1"><i class="ri-star-fill"></i> Featured</span>@endif
                            @if($t->rating)<span class="text-warning small ms-1">{{ str_repeat('★', $t->rating) }}</span>@endif
                            <div class="small text-muted">{{ $t->byline() }}@if($t->source !== 'staff') · sent by {{ $t->source === 'link' ? 'the client' : 'the student' }}@endif · {{ $t->created_at->format('j M Y') }}</div>
                            <div class="small mt-1">“{{ \Illuminate\Support\Str::limit($t->quote, 220) }}”</div>
                        </div>
                        <span class="badge bg-{{ ['pending' => 'warning', 'published' => 'success', 'hidden' => 'secondary'][$t->status] }}-subtle text-{{ ['pending' => 'warning', 'published' => 'success', 'hidden' => 'secondary'][$t->status] }} text-nowrap">{{ \App\Models\Website\Testimonial::STATUSES[$t->status] }}</span>
                        <div class="d-flex gap-1">
                            @if($t->status !== 'published')<form method="POST" action="{{ route('website.testimonials.review', $t) }}">@csrf<input type="hidden" name="status" value="published"><button class="btn btn-sm btn-success" title="Publish"><i class="ri-check-line"></i></button></form>@endif
                            @if($t->status !== 'hidden')<form method="POST" action="{{ route('website.testimonials.review', $t) }}">@csrf<input type="hidden" name="status" value="hidden"><button class="btn btn-sm btn-soft-secondary" title="Hide"><i class="ri-eye-off-line"></i></button></form>@endif
                            <a href="{{ route('website.testimonials.edit', $t) }}" class="btn btn-sm btn-soft-primary">Edit</a>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-muted">No testimonials here yet.</div>
                @endforelse
            </x-cb.card>
        </div>
        <div class="col-xl-3">
            <x-cb.card title="Ask a client" icon="ri-links-line">
                <p class="small text-muted">Make a link a client can use to write a testimonial. No sign-in needed; it works for 60 days.</p>
                <form method="POST" action="{{ route('website.testimonials.link') }}">
                    @csrf
                    <input name="organisation" class="form-control mb-2" maxlength="150" placeholder="Company name (optional, filled in for them)">
                    <button class="btn btn-soft-primary w-100"><i class="ri-link me-1"></i>Make a link</button>
                </form>
                @if($link)
                    <div class="input-group input-group-sm mt-3">
                        <input class="form-control" value="{{ $link }}" readonly id="clientLink" onclick="this.select()">
                        <button class="btn btn-primary" type="button" onclick="navigator.clipboard.writeText(document.getElementById('clientLink').value);this.textContent='Copied'">Copy</button>
                    </div>
                @endif
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@include('modules.website.students._styles')
@endsection
