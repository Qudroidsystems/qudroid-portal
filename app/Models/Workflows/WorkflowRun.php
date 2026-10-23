<?php

namespace App\Models\Workflows;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class WorkflowRun extends Model
{
    public const STATUSES = [
        'queued' => ['Queued', 'secondary'],
        'running' => ['Running', 'info'],
        'waiting' => ['Waiting', 'warning'],
        'done' => ['Done', 'success'],
        'stopped' => ['Stopped by a condition', 'secondary'],
        'failed' => ['Failed', 'danger'],
        'skipped' => ['Skipped', 'secondary'],
    ];

    protected $fillable = ['workflow_id', 'status', 'subject_type', 'subject_id', 'title', 'payload', 'step', 'resume_at', 'log', 'error', 'dedupe', 'depth', 'is_test', 'finished_at'];

    protected $casts = ['payload' => 'array', 'log' => 'array', 'resume_at' => 'datetime', 'finished_at' => 'datetime', 'is_test' => 'boolean'];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function statusLabel(): string
    {
        return self::STATUSES[$this->status][0] ?? ucfirst($this->status);
    }

    public function statusColor(): string
    {
        return self::STATUSES[$this->status][1] ?? 'secondary';
    }
}
