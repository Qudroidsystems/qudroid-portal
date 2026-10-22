<?php

namespace App\Models\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Project extends Model
{
    public const STATUSES = ['planning' => 'Planning', 'active' => 'Active', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];
    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High', 'urgent' => 'Urgent'];
    public const OPEN = ['planning', 'active', 'on_hold'];

    protected $fillable = ['code', 'name', 'description', 'client_name', 'client_id', 'company_id', 'status', 'priority', 'starts_on', 'due_on',
        'budget', 'currency', 'color', 'manager_id', 'created_by', 'task_seq', 'completed_at'];

    protected $casts = ['starts_on' => 'date', 'due_on' => 'date', 'budget' => 'decimal:2', 'completed_at' => 'datetime'];

    public function getRouteKeyName(): string
    {
        return 'code';
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'project_members')->withPivot('role')->withTimestamps();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function milestones(): HasMany
    {
        return $this->hasMany(Milestone::class)->orderBy('position')->orderBy('due_on');
    }

    public function timeEntries(): HasMany
    {
        return $this->hasMany(TimeEntry::class);
    }

    public function manager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(User::class, 'client_id');
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Crm\Company::class);
    }

    public function scopeOpen(Builder $q): Builder
    {
        return $q->whereIn('status', self::OPEN);
    }

    /** Projects this person can see: all of them with Manage projects, otherwise those they belong to. */
    public function scopeVisibleTo(Builder $q, User $user): Builder
    {
        return $user->can('Manage projects') ? $q : $q->whereHas('members', fn ($m) => $m->where('user_id', $user->id));
    }

    public function isOpen(): bool
    {
        return in_array($this->status, self::OPEN, true);
    }

    public function isOverdue(): bool
    {
        return $this->isOpen() && $this->due_on && $this->due_on->lt(today());
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
    }
}
