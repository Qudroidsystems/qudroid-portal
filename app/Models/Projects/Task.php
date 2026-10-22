<?php

namespace App\Models\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Task extends Model
{
    public const STATUSES = ['todo' => 'To do', 'in_progress' => 'In progress', 'review' => 'In review', 'done' => 'Done'];

    protected $table = 'project_tasks';

    protected $fillable = ['project_id', 'milestone_id', 'number', 'title', 'description', 'status', 'priority', 'assignee_id', 'reporter_id',
        'starts_on', 'due_on', 'estimate_minutes', 'checklist', 'client_visible', 'position', 'completed_at'];

    protected $casts = ['starts_on' => 'date', 'due_on' => 'date', 'checklist' => 'array', 'client_visible' => 'boolean', 'completed_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function milestone(): BelongsTo
    {
        return $this->belongsTo(Milestone::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    public function reporter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reporter_id');
    }

    public function comments(): HasMany
    {
        return $this->hasMany(TaskComment::class)->oldest();
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->where('status', '!=', 'done');
    }

    /** "WEB-14" */
    public function getKeyLabelAttribute(): string
    {
        return ($this->project?->code ?? '#') . '-' . $this->number;
    }

    public function isOverdue(): bool
    {
        return $this->status !== 'done' && $this->due_on && $this->due_on->lt(today());
    }

    /** [done, total] for the checklist. */
    public function checklistProgress(): array
    {
        $items = collect($this->checklist ?? []);

        return [$items->where('done', true)->count(), $items->count()];
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
