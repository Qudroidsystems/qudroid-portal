<?php

namespace App\Models\Projects;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Milestone extends Model
{
    protected $table = 'project_milestones';

    protected $fillable = ['project_id', 'title', 'description', 'due_on', 'completed_at', 'position'];

    protected $casts = ['due_on' => 'date', 'completed_at' => 'datetime'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function isOverdue(): bool
    {
        return !$this->completed_at && $this->due_on && $this->due_on->lt(today());
    }
}
