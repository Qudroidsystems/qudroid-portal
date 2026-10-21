@extends('layouts.master')

@php $F = fn ($p) => \App\Services\Website\WebsiteContent::fileUrl($p); @endphp

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero :title="$album->title" icon="ri-image-2-line" :subtitle="$album->photos->count() . ' photos'" :back="route('website.admin.albums')" back-label="Gallery">
        <x-slot:actions><a class="cb-hero-btn" target="_blank" href="{{ route('site.album', $album->slug) }}"><i class="ri-external-link-line me-1"></i>View album</a></x-slot:actions>
    </x-cb.hero>
    @include('website.admin._nav')

    <div class="row g-3">
        <div class="col-xl-4">
            <x-cb.card title="Upload photos" icon="ri-upload-cloud-2-line">
                <form method="POST" action="{{ route('website.admin.album.upload', $album->id) }}" enctype="multipart/form-data" id="upForm">@csrf
                    <label for="photos" class="d-block border border-2 rounded-3 p-4 text-center" style="border-style:dashed!important;cursor:pointer" id="drop">
                        <i class="ri-image-add-line" style="font-size:2.4rem;color:#94a3b8"></i>
                        <div class="fw-semibold">Choose or drop photos</div>
                        <div class="small text-muted">JPG / PNG, up to 12 MB each, 40 at a time</div>
                    </label>
                    <input type="file" name="photos[]" id="photos" accept="image/*" multiple hidden>
                    <div id="upStatus" class="small mt-2"></div>
                    <div class="progress mt-2 d-none" id="upBar" style="height:8px"><div class="progress-bar" style="width:0%"></div></div>
                </form>
            </x-cb.card>
            <x-cb.card title="Album details" icon="ri-information-line">
                <form method="POST" action="{{ route('website.admin.album.save', $album->id) }}">@csrf
                    <label class="form-label">Title</label><input name="title" class="form-control mb-2" value="{{ $album->title }}" required>
                    <label class="form-label">Date</label><input type="date" name="taken_on" class="form-control mb-2" value="{{ optional($album->taken_on)->format('Y-m-d') }}">
                    <label class="form-label">Description</label><textarea name="description" class="form-control mb-2" rows="2">{{ $album->description }}</textarea>
                    <label class="form-label">Order</label><input type="number" name="sort" min="0" class="form-control mb-2" value="{{ $album->sort }}">
                    <div class="form-check form-switch mb-3"><input type="hidden" name="published" value="0"><input class="form-check-input" type="checkbox" role="switch" name="published" value="1" id="pub" @checked($album->published)><label class="form-check-label" for="pub">Show on the website</label></div>
                    <button class="btn btn-primary w-100">Save details</button>
                </form>
                <form method="POST" action="{{ route('website.admin.album.delete', $album->id) }}" class="mt-2" onsubmit="return confirm('Delete this album and all its photos?')">@csrf @method('DELETE')<button class="btn btn-outline-danger w-100 btn-sm">Delete album</button></form>
            </x-cb.card>
        </div>
        <div class="col-xl-8">
            <form method="POST" action="{{ route('website.admin.album.photos', $album->id) }}">@csrf
                <x-cb.card title="Photos" icon="ri-gallery-line" :count="$album->photos->count()">
                    <x-slot:tools><button class="btn btn-sm btn-primary"><i class="ri-save-3-line me-1"></i>Save captions, order &amp; cover</button></x-slot:tools>
                    @if($album->photos->isEmpty())<p class="text-muted mb-0">No photos yet — upload some on the left.</p>@endif
                    <div class="row g-3">
                        @foreach($album->photos as $p)
                            <div class="col-sm-6 col-lg-4">
                                <div class="border rounded-3 overflow-hidden h-100">
                                    <a href="{{ $F($p->path) }}" target="_blank"><img src="{{ $F($p->thumb ?: $p->path) }}" style="width:100%;aspect-ratio:4/3;object-fit:cover"></a>
                                    <div class="p-2">
                                        <input name="captions[{{ $p->id }}]" class="form-control form-control-sm mb-1" value="{{ $p->caption }}" placeholder="Caption">
                                        <div class="d-flex align-items-center gap-2 small">
                                            <input type="number" name="order[{{ $p->id }}]" value="{{ $p->sort }}" min="0" class="form-control form-control-sm" style="width:70px" title="Order">
                                            <label class="mb-0"><input type="radio" name="cover" value="{{ $p->id }}" @checked($album->cover_photo_id === $p->id)> Cover</label>
                                            <label class="mb-0 text-danger ms-auto"><input type="checkbox" name="delete[]" value="{{ $p->id }}"> Delete</label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </x-cb.card>
            </form>
        </div>
    </div>
</div></div></div>

<script>
(function () {
    const input = document.getElementById('photos'), drop = document.getElementById('drop'), status = document.getElementById('upStatus'), bar = document.getElementById('upBar');
    const token = document.querySelector('#upForm input[name=_token]').value;
    async function send(files) {
        files = Array.from(files).filter(f => f.type.startsWith('image/'));
        if (!files.length) return;
        bar.classList.remove('d-none');
        let done = 0, failed = 0;
        // Upload in small batches so large selections don't exceed the server's post size.
        for (let i = 0; i < files.length; i += 4) {
            const fd = new FormData();
            fd.append('_token', token);
            files.slice(i, i + 4).forEach(f => fd.append('photos[]', f));
            status.textContent = 'Uploading ' + Math.min(i + 4, files.length) + ' of ' + files.length + '…';
            try {
                const r = await fetch('{{ route('website.admin.album.upload', $album->id) }}', { method: 'POST', body: fd, headers: { Accept: 'application/json' } });
                if (!r.ok) throw new Error(r.status);
                done += Math.min(4, files.length - i);
            } catch (e) { failed += Math.min(4, files.length - i); }
            bar.firstElementChild.style.width = Math.round(Math.min(i + 4, files.length) / files.length * 100) + '%';
        }
        status.textContent = done + ' uploaded' + (failed ? ', ' + failed + ' failed (too large?)' : '') + '. Refreshing…';
        setTimeout(() => location.reload(), 600);
    }
    input.addEventListener('change', () => send(input.files));
    ['dragover', 'dragenter'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.add('bg-light'); }));
    ['dragleave', 'drop'].forEach(e => drop.addEventListener(e, ev => { ev.preventDefault(); drop.classList.remove('bg-light'); }));
    drop.addEventListener('drop', ev => send(ev.dataTransfer.files));
})();
</script>
@endsection
