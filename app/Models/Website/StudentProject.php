<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Something a student built, on their website profile. */
class StudentProject extends Model
{
    protected $table = 'site_student_projects';

    protected $fillable = ['profile_id', 'course_id', 'title', 'summary', 'image', 'url', 'repo_url', 'tags', 'position'];

    protected $casts = ['tags' => 'array', 'position' => 'integer'];

    public function profile(): BelongsTo
    {
        return $this->belongsTo(StudentProfile::class, 'profile_id');
    }

    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academy\Course::class)->withTrashed();
    }

    public function imageUrl(): ?string
    {
        return Product::url($this->image);
    }
}
