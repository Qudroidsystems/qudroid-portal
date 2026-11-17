<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A comment between a student and their instructor on a piece of work (optionally on one line of code). */
class AssessmentComment extends Model
{
    protected $table = 'academy_assessment_comments';

    protected $fillable = ['submission_id', 'attempt_id', 'user_id', 'body', 'line', 'question_id'];

    protected $casts = ['line' => 'integer'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function submission(): BelongsTo
    {
        return $this->belongsTo(Submission::class);
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(Attempt::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }
}
