{{-- Actions for one folder or file. Needs $item, $stars, $inTrash. --}}
@php
    $isFolder = $item instanceof \App\Models\Drive\Folder;
    $key = ['type' => $item::TYPE, 'id' => $item->id];
    $starred = isset($stars[$item::TYPE . ':' . $item->id]);
@endphp
<div class="dropdown">
    <button class="btn btn-sm btn-ghost-secondary btn-icon" data-bs-toggle="dropdown" aria-label="Actions for {{ $item->name }}"><i class="ri-more-2-fill"></i></button>
    <ul class="dropdown-menu dropdown-menu-end">
        @if($inTrash)
            <li><form method="POST" action="{{ route('drive.restore', $key) }}">@csrf<button class="dropdown-item"><i class="ri-arrow-go-back-line me-2"></i>Restore</button></form></li>
            <li><form method="POST" action="{{ route('drive.destroy', $key) }}" onsubmit="return confirm('Delete {{ addslashes($item->name) }} for good? This can\'t be undone.')">@csrf @method('DELETE')<button class="dropdown-item text-danger"><i class="ri-delete-bin-line me-2"></i>Delete for good</button></form></li>
        @else
            <li><a class="dropdown-item" href="{{ $isFolder ? route('drive.folder', $item) : route('drive.file', $item) }}"><i class="ri-eye-line me-2"></i>Open</a></li>
            <li><a class="dropdown-item" href="{{ $isFolder ? route('drive.folder.zip', $item) : route('drive.file.download', $item) }}"><i class="ri-download-2-line me-2"></i>{{ $isFolder ? 'Download as zip' : 'Download' }}</a></li>
            <li><a class="dropdown-item" href="{{ route('drive.share', $key) }}"><i class="ri-user-add-line me-2"></i>Share</a></li>
            <li><button type="button" class="dropdown-item" data-drive-rename="{{ route('drive.rename', $key) }}" data-name="{{ $item->name }}"><i class="ri-edit-line me-2"></i>Rename</button></li>
            <li><a class="dropdown-item" href="{{ route('drive.move', $key) }}"><i class="ri-folder-transfer-line me-2"></i>Move</a></li>
            <li><form method="POST" action="{{ route('drive.star', $key) }}">@csrf<button class="dropdown-item"><i class="{{ $starred ? 'ri-star-fill drive-star' : 'ri-star-line' }} me-2"></i>{{ $starred ? 'Remove star' : 'Star' }}</button></form></li>
            <li><hr class="dropdown-divider"></li>
            <li><form method="POST" action="{{ route('drive.trash.item', $key) }}">@csrf<input type="hidden" name="stay" value="1"><button class="dropdown-item text-danger"><i class="ri-delete-bin-6-line me-2"></i>Move to trash</button></form></li>
        @endif
    </ul>
</div>
