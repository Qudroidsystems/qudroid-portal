@extends('layouts.master')
@include('modules.drive._styles')

@section('content')
<div class="main-content"><div class="page-content"><div class="container-fluid">
    <x-cb.hero title="From chats" icon="ri-chat-3-line" subtitle="Files people sent in your conversations. Save one to My Drive to keep your own copy." />

    @include('partials.flash')

    <div class="row g-3">
        <div class="col-lg-3">@include('modules.drive._side')</div>
        <div class="col-lg-9">
            <x-cb.card :flush="true">
                @if($messages->isEmpty())
                    <div class="empty-state py-5"><i class="ri-chat-3-line"></i><h6>No files in your chats yet</h6></div>
                @else
                    <div class="table-responsive">
                        <table class="table table-hover drive-table mb-0">
                            <thead class="table-light"><tr><th>Name</th><th class="d-none d-md-table-cell">Chat</th><th class="d-none d-sm-table-cell">Sent</th><th class="d-none d-sm-table-cell text-end">Size</th><th style="width:48px"><span class="visually-hidden">Actions</span></th></tr></thead>
                            <tbody>
                                @foreach($messages as $m)
                                    <tr>
                                        <td style="max-width:0;width:45%"><div class="drive-name">
                                            <i class="{{ \App\Models\Drive\File::iconFor($m->attachment_mime, strtolower(pathinfo((string) $m->attachment_name, PATHINFO_EXTENSION))) }} ico"></i>
                                            <a href="{{ route('connect.messages.file', $m) }}" target="_blank" rel="noopener" title="{{ $m->attachment_name }}">{{ $m->attachment_name }}</a>
                                        </div></td>
                                        <td class="d-none d-md-table-cell small"><a href="{{ route('connect.show', $m->conversation) }}" class="text-muted">{{ $m->conversation->titleFor(auth()->user()) }}</a></td>
                                        <td class="d-none d-sm-table-cell small text-muted">{{ $m->user?->name }} · {{ $m->created_at->diffForHumans() }}</td>
                                        <td class="d-none d-sm-table-cell small text-muted text-end text-nowrap">{{ \App\Models\Drive\File::humanSize($m->attachment_size) }}</td>
                                        <td>
                                            <div class="dropdown">
                                                <button class="btn btn-sm btn-ghost-secondary btn-icon" data-bs-toggle="dropdown" aria-label="Actions for {{ $m->attachment_name }}"><i class="ri-more-2-fill"></i></button>
                                                <ul class="dropdown-menu dropdown-menu-end">
                                                    <li><a class="dropdown-item" href="{{ route('connect.messages.file', [$m, 'download' => 1]) }}"><i class="ri-download-2-line me-2"></i>Download</a></li>
                                                    <li><form method="POST" action="{{ route('drive.chats.save', $m) }}">@csrf<button class="dropdown-item"><i class="ri-hard-drive-2-line me-2"></i>Save to My Drive</button></form></li>
                                                    <li><a class="dropdown-item" href="{{ route('connect.show', $m->conversation) }}"><i class="ri-chat-3-line me-2"></i>Open chat</a></li>
                                                </ul>
                                            </div>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </x-cb.card>
        </div>
    </div>
</div></div></div>
@endsection
