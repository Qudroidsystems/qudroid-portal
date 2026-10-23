<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TranscriptLine extends Model
{
    protected $table = 'connect_call_transcript';

    public $timestamps = false;

    protected $fillable = ['call_id', 'user_id', 'text', 'spoken_at'];

    protected $casts = ['spoken_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
