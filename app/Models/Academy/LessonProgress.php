<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LessonProgress extends Model
{
    protected $table = 'academy_lesson_progress';

    protected $fillable = ['enrollment_id', 'lesson_id', 'first_viewed_at', 'completed_at'];

    protected $casts = ['first_viewed_at' => 'datetime', 'completed_at' => 'datetime'];

    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }
}
