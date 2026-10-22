<?php

namespace App\Models\Hr;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LeaveRequest extends Model
{
    public const STATUSES = ['pending' => 'Waiting for approval', 'approved' => 'Approved', 'rejected' => 'Declined', 'cancelled' => 'Cancelled'];

    protected $table = 'hr_leave_requests';

    protected $fillable = ['employee_id', 'leave_type_id', 'starts_on', 'ends_on', 'days', 'reason', 'status', 'decided_by', 'decided_at', 'decision_note'];

    protected $casts = ['starts_on' => 'date', 'ends_on' => 'date', 'days' => 'float', 'decided_at' => 'datetime'];

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function type(): BelongsTo
    {
        return $this->belongsTo(LeaveType::class, 'leave_type_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
