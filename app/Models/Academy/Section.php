<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A chapter of a course, holding lessons in order. */
class Section extends Model
{
    protected $table = 'academy_sections';

    protected $fillable = ['course_id', 'title', 'description', 'position'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'section_id')->orderBy('position')->orderBy('id');
    }
}
