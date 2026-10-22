<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** A quiz or an assignment on a course. */
class Assessment extends Model
{
    public const TYPES = ['quiz' => 'Quiz', 'assignment' => 'Assignment'];

    public const ICONS = ['quiz' => 'ri-questionnaire-line', 'assignment' => 'ri-draft-line'];

    protected $table = 'academy_assessments';

    protected $fillable = [
        'course_id', 'type', 'title', 'instructions', 'weight', 'pass_mark', 'max_score', 'max_attempts',
        'time_limit_minutes', 'shuffle', 'show_answers', 'due_at', 'is_published', 'position', 'created_by',
    ];

    protected $casts = [
        'weight'             => 'decimal:2',
        'max_score'          => 'decimal:2',
        'pass_mark'          => 'integer',
        'max_attempts'       => 'integer',
        'time_limit_minutes' => 'integer',
        'shuffle'            => 'boolean',
        'show_answers'       => 'boolean',
        'is_published'       => 'boolean',
        'due_at'             => 'datetime',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class)->withTrashed();
    }

    public function questions(): HasMany
    {
        return $this->hasMany(Question::class)->orderBy('position')->orderBy('id');
    }

    public function attempts(): HasMany
    {
        return $this->hasMany(Attempt::class);
    }

    public function submissions(): HasMany
    {
        return $this->hasMany(Submission::class);
    }

    public function isQuiz(): bool
    {
        return $this->type === 'quiz';
    }

    public function totalPoints(): float
    {
        return $this->isQuiz() ? (float) $this->questions()->sum('points') : (float) $this->max_score;
    }

    public function isPastDue(): bool
    {
        return $this->due_at !== null && now()->gt($this->due_at);
    }

    public function instructionsHtml(): string
    {
        return $this->instructions ? Str::markdown($this->instructions, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : '';
    }

    public function getIconAttribute(): string
    {
        return self::ICONS[$this->type] ?? 'ri-file-list-line';
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
