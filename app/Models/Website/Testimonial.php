<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** What a student or a client company says about us. Shown once staff publish it. */
class Testimonial extends Model
{
    protected $table = 'site_testimonials';

    public const KINDS = ['student' => 'Student', 'client' => 'Client'];

    public const STATUSES = ['pending' => 'Waiting for approval', 'published' => 'On the website', 'hidden' => 'Hidden'];

    protected $fillable = ['kind', 'name', 'role', 'organisation', 'quote', 'rating', 'photo', 'video_url', 'website_url', 'course_id', 'student_id',
        'user_id', 'source', 'status', 'featured', 'position', 'reviewed_by'];

    protected $casts = ['rating' => 'integer', 'featured' => 'boolean', 'position' => 'integer'];

    public function course(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academy\Course::class)->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(\App\Models\Academy\Student::class);
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderByDesc('featured')->orderBy('position')->orderByDesc('id');
    }

    public function photoUrl(): ?string
    {
        return Product::url($this->photo);
    }

    public function initials(): string
    {
        return collect(preg_split('/\s+/', trim((string) ($this->kind === 'client' && $this->organisation ? $this->organisation : $this->name))))
            ->filter()->take(2)->map(fn ($p) => mb_strtoupper(mb_substr($p, 0, 1)))->implode('');
    }

    /** "Graduate · Web Development" / "CEO, Acme Ltd". */
    public function byline(): string
    {
        if ($this->kind === 'client') {
            return collect([$this->role, $this->organisation])->filter()->implode(', ');
        }

        return collect([$this->role, $this->course?->title])->filter()->implode(' · ');
    }

    /** The student's public profile, when there is one. */
    public function profile(): ?StudentProfile
    {
        return $this->student_id ? StudentProfile::published()->where('student_id', $this->student_id)->first() : null;
    }
}
