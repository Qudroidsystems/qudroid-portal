<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * An assessment on a course. Its type is how it works: a quiz (questions,
 * marked automatically except essays and code), an assignment (students hand
 * in text, code or a file; the instructor grades it) or manual (done in class;
 * the instructor enters the scores). Its kind is what students see it as.
 */
class Assessment extends Model
{
    public const TYPES = ['quiz' => 'Quiz', 'assignment' => 'Assignment', 'manual' => 'Marked in class'];

    /** kind => [label, type, icon] */
    public const KINDS = [
        'quiz'          => ['Quiz', 'quiz', 'ri-questionnaire-line'],
        'test'          => ['Test', 'quiz', 'ri-file-list-3-line'],
        'exam'          => ['Exam', 'quiz', 'ri-file-shield-2-line'],
        'assignment'    => ['Assignment', 'assignment', 'ri-draft-line'],
        'project'       => ['Project', 'assignment', 'ri-rocket-2-line'],
        'coding'        => ['Coding exercise', 'assignment', 'ri-code-s-slash-line'],
        'practical'     => ['Practical / lab', 'manual', 'ri-flask-line'],
        'participation' => ['Class participation', 'manual', 'ri-hand-heart-line'],
        'oral'          => ['Oral / presentation', 'manual', 'ri-slideshow-line'],
        'other'         => ['Other assessment', 'manual', 'ri-medal-line'],
    ];

    public const ICONS = ['quiz' => 'ri-questionnaire-line', 'assignment' => 'ri-draft-line', 'manual' => 'ri-medal-line'];

    /** What students hand in for an assignment. */
    public const ANSWER_FORMATS = [
        'text'      => 'Written answer',
        'code'      => 'Code (with a code editor)',
        'text_code' => 'Written answer and code',
        'file'      => 'A file only',
    ];

    /** Languages the code editor knows: key => [label, CodeMirror mode]. */
    public const LANGUAGES = [
        'javascript' => ['JavaScript', 'javascript'],
        'typescript' => ['TypeScript', 'text/typescript'],
        'python'     => ['Python', 'python'],
        'php'        => ['PHP', 'application/x-httpd-php'],
        'html'       => ['HTML', 'htmlmixed'],
        'css'        => ['CSS', 'css'],
        'sql'        => ['SQL', 'sql'],
        'java'       => ['Java', 'text/x-java'],
        'kotlin'     => ['Kotlin', 'text/x-kotlin'],
        'dart'       => ['Dart', 'dart'],
        'c'          => ['C', 'text/x-csrc'],
        'cpp'        => ['C++', 'text/x-c++src'],
        'csharp'     => ['C#', 'text/x-csharp'],
        'go'         => ['Go', 'go'],
        'ruby'       => ['Ruby', 'ruby'],
        'swift'      => ['Swift', 'swift'],
        'solidity'   => ['Solidity', 'text/x-java'],
        'shell'      => ['Shell', 'shell'],
        'plain'      => ['Plain text', 'text/plain'],
    ];

    protected $table = 'academy_assessments';

    protected $fillable = [
        'course_id', 'type', 'kind', 'title', 'instructions', 'answer_format', 'code_language', 'starter_code', 'allow_file', 'weight', 'pass_mark', 'max_score', 'max_attempts',
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
        'allow_file'         => 'boolean',
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

    /** Marked in class: no hand-in, the instructor enters the scores. */
    public function isManual(): bool
    {
        return $this->type === 'manual';
    }

    public function isAssignment(): bool
    {
        return $this->type === 'assignment';
    }

    public function wantsCode(): bool
    {
        return $this->isAssignment() && in_array($this->answer_format, ['code', 'text_code'], true);
    }

    public function wantsText(): bool
    {
        return $this->isAssignment() && in_array($this->answer_format ?: 'text', ['text', 'text_code'], true);
    }

    public function acceptsFile(): bool
    {
        return $this->isAssignment() && ($this->answer_format === 'file' || $this->allow_file);
    }

    public static function typeOfKind(string $kind): string
    {
        return self::KINDS[$kind][1] ?? 'assignment';
    }

    public function getKindKeyAttribute(): string
    {
        return $this->kind && isset(self::KINDS[$this->kind]) ? $this->kind : (string) $this->type;
    }

    public function getLanguageLabelAttribute(): ?string
    {
        return $this->code_language ? (self::LANGUAGES[$this->code_language][0] ?? $this->code_language) : null;
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
        return self::KINDS[$this->kind_key][2] ?? self::ICONS[$this->type] ?? 'ri-file-list-line';
    }

    /** What students see it as: "Test", "Project", "Coding exercise"... */
    public function getTypeLabelAttribute(): string
    {
        return self::KINDS[$this->kind_key][0] ?? self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }
}
