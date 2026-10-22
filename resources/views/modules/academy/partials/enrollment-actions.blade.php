{{-- Row actions for one enrollment: open access, complete, withdraw / cancel. --}}
<div class="d-flex gap-1 justify-content-end flex-wrap">
    @if($e->isOpen() && !$e->access_granted)
        @can('Grant course access')
            <button type="button" class="btn btn-sm btn-soft-success" data-bs-toggle="collapse" data-bs-target="#access-{{ $e->id }}">Open access</button>
        @endcan
    @endif
    @if($e->status === 'active')
        <form method="POST" action="{{ route('academy.enrollments.complete', $e) }}" onsubmit="return confirm('Mark this enrollment completed?')">@csrf<button class="btn btn-sm btn-soft-primary">Completed</button></form>
    @endif
    @if($e->isOpen())
        <button type="button" class="btn btn-sm btn-soft-danger" data-bs-toggle="collapse" data-bs-target="#end-{{ $e->id }}">End</button>
    @endif
</div>
@if($e->isOpen())
    @if(!$e->access_granted)
        @can('Grant course access')
            <form id="access-{{ $e->id }}" class="collapse mt-2" method="POST" action="{{ route('academy.enrollments.access', $e) }}">
                @csrf
                <div class="input-group input-group-sm"><input name="reason" class="form-control" required maxlength="255" placeholder="Reason, e.g. instalment plan approved by MD"><button class="btn btn-success">Open</button></div>
            </form>
        @endcan
    @endif
    <form id="end-{{ $e->id }}" class="collapse mt-2" method="POST" action="{{ route('academy.enrollments.end', $e) }}">
        @csrf
        <div class="input-group input-group-sm">
            <select name="status" class="form-select" style="max-width: 130px"><option value="withdrawn">Withdrawn</option><option value="cancelled">Cancelled</option></select>
            <input name="note" class="form-control" maxlength="500" placeholder="Note (optional)">
            <button class="btn btn-danger">End</button>
        </div>
    </form>
@endif
