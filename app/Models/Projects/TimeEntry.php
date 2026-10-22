<?php

namespace App\Models\Projects;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimeEntry extends Model
{
    protected $table = 'project_time_entries';

    protected $fillable = ['project_id', 'task_id', 'user_id', 'date', 'minutes', 'note', 'billable'];

    protected $casts = ['date' => 'date', 'billable' => 'boolean'];

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** 90 → "1h 30m" */
    public static function format(int $minutes): string
    {
        $h = intdiv($minutes, 60);
        $m = $minutes % 60;

        return trim(($h ? $h . 'h ' : '') . ($m || !$h ? $m . 'm' : ''));
    }
}
