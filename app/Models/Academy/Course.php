<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/** A course students register and pay for. Each run of it is a Cohort. */
class Course extends Model
{
    use SoftDeletes;

    public const LEVELS = ['beginner' => 'Beginner', 'intermediate' => 'Intermediate', 'advanced' => 'Advanced'];
    public const MODES = ['physical' => 'Physical', 'online' => 'Online', 'hybrid' => 'Hybrid'];
    public const STATUSES = ['draft' => 'Draft', 'published' => 'Published', 'archived' => 'Archived'];

    protected $table = 'academy_courses';

    protected $fillable = [
        'programme_id', 'code', 'title', 'slug', 'summary', 'description', 'level',
        'delivery_mode', 'duration_weeks', 'fee', 'currency', 'installments_allowed',
        'min_deposit', 'pass_grade', 'certificate_enabled', 'status', 'created_by',
    ];

    protected $casts = [
        'fee'                  => 'decimal:2',
        'min_deposit'          => 'decimal:2',
        'installments_allowed' => 'boolean',
        'duration_weeks'       => 'integer',
        'pass_grade'           => 'integer',
        'certificate_enabled'  => 'boolean',
    ];

    protected $attributes = ['pass_grade' => 50, 'certificate_enabled' => true];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->slug)) $c->slug = Str::slug($c->title) . '-' . Str::lower(Str::random(4));
        });
    }

    public function programme(): BelongsTo
    {
        return $this->belongsTo(Programme::class, 'programme_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function cohorts(): HasMany
    {
        return $this->hasMany(Cohort::class, 'course_id')->orderByDesc('starts_on');
    }

    public function assessments(): HasMany
    {
        return $this->hasMany(Assessment::class, 'course_id')->orderBy('position')->orderBy('id');
    }

    public function sections(): HasMany
    {
        return $this->hasMany(Section::class, 'course_id')->orderBy('position')->orderBy('id');
    }

    public function lessons(): HasMany
    {
        return $this->hasMany(Lesson::class, 'course_id')->orderBy('position')->orderBy('id');
    }

    public function enrollments(): HasMany
    {
        return $this->hasMany(Enrollment::class, 'course_id');
    }

    /** Courses a student must have completed before enrolling on this one. */
    public function prerequisites(): BelongsToMany
    {
        return $this->belongsToMany(self::class, 'academy_course_prerequisites', 'course_id', 'prerequisite_id');
    }

    public function scopePublished(Builder $q): Builder
    {
        return $q->where('status', 'published');
    }

    public function isPublished(): bool
    {
        return $this->status === 'published';
    }

    public function getLevelLabelAttribute(): string
    {
        return self::LEVELS[$this->level] ?? ucfirst((string) $this->level);
    }

    public function getModeLabelAttribute(): string
    {
        return self::MODES[$this->delivery_mode] ?? ucfirst((string) $this->delivery_mode);
    }
}
