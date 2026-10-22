<div class="list-group-item d-flex align-items-center gap-2 flex-wrap">
    <i class="{{ $lesson->icon }} fs-5 text-muted"></i>
    <div class="flex-grow-1">
        <a href="{{ route('academy.content.lessons.edit', [$course, $lesson]) }}" class="fw-semibold">{{ $lesson->title }}</a>
        <span class="small text-muted ms-1">{{ $lesson->type_label }}@if($lesson->duration_minutes) · {{ $lesson->duration_minutes }} min @endif</span>
        @if(!$lesson->is_published)<span class="badge bg-secondary-subtle text-secondary ms-1">Draft</span>@endif
        @if($lesson->is_preview)<span class="badge bg-info-subtle text-info ms-1">Free preview</span>@endif
    </div>
    <a href="{{ route('academy.learn.lesson', [$course, $lesson]) }}" class="btn btn-sm btn-light" title="View as student"><i class="ri-eye-line"></i></a>
    <form method="POST" action="{{ route('academy.content.lessons.move', [$course, $lesson]) }}">@csrf<input type="hidden" name="direction" value="up"><button class="btn btn-sm btn-light" title="Move up" @disabled($first)><i class="ri-arrow-up-line"></i></button></form>
    <form method="POST" action="{{ route('academy.content.lessons.move', [$course, $lesson]) }}">@csrf<input type="hidden" name="direction" value="down"><button class="btn btn-sm btn-light" title="Move down" @disabled($last)><i class="ri-arrow-down-line"></i></button></form>
    <form method="POST" action="{{ route('academy.content.lessons.destroy', [$course, $lesson]) }}" onsubmit="return confirm('Delete this lesson? Students lose their progress on it.')">@csrf @method('DELETE')<button class="btn btn-sm btn-soft-danger" title="Delete"><i class="ri-delete-bin-line"></i></button></form>
</div>
