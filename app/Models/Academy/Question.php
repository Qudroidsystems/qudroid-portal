<?php

namespace App\Models\Academy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One quiz question. Everything except essays is marked automatically. */
class Question extends Model
{
    public const TYPES = [
        'single'       => 'One correct answer',
        'multiple'     => 'Several correct answers',
        'boolean'      => 'True or false',
        'short_answer' => 'Short typed answer',
        'essay'        => 'Essay (marked by a teacher)',
    ];

    protected $table = 'academy_questions';

    protected $fillable = ['assessment_id', 'type', 'question', 'options', 'correct', 'accepted_answers', 'explanation', 'points', 'position'];

    protected $casts = ['options' => 'array', 'correct' => 'array', 'accepted_answers' => 'array', 'points' => 'decimal:2'];

    public function assessment(): BelongsTo
    {
        return $this->belongsTo(Assessment::class);
    }

    public function isChoice(): bool
    {
        return in_array($this->type, ['single', 'multiple', 'boolean'], true);
    }

    public function isManual(): bool
    {
        return $this->type === 'essay';
    }

    /**
     * Points for an answer. Choice questions need exactly the right options
     * (several-answer questions give part marks: right picks minus wrong
     * picks). Short answers match any accepted answer, ignoring case and
     * extra spaces. Essays score 0 here; a teacher marks them.
     */
    public function award(mixed $answer): float
    {
        $points = (float) $this->points;

        if ($this->isManual()) return 0.0;

        if ($this->isChoice()) {
            $picked = array_values(array_unique(array_map('intval', (array) $answer)));
            $key = array_map('intval', (array) $this->correct);
            sort($picked);
            sort($key);
            if ($key && $picked === $key) return $points;
            if ($this->type === 'multiple' && $key) {
                $right = count(array_intersect($picked, $key));
                $wrong = count(array_diff($picked, $key));

                return round($points * max(0, $right - $wrong) / count($key), 2);
            }

            return 0.0;
        }

        $given = $this->normalise(is_array($answer) ? implode(' ', $answer) : (string) $answer);
        if ($given === '') return 0.0;
        foreach ((array) $this->accepted_answers as $accepted) {
            if ($this->normalise((string) $accepted) === $given) return $points;
        }

        return 0.0;
    }

    protected function normalise(string $text): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/u', ' ', $text)));
    }
}
