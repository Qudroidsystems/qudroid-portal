<?php

namespace App\Models\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TaskComment extends Model
{
    protected $table = 'project_task_comments';

    protected $fillable = ['task_id', 'user_id', 'body', 'is_internal', 'is_event', 'attachment_path', 'attachment_name'];

    protected $casts = ['is_internal' => 'boolean', 'is_event' => 'boolean'];

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
