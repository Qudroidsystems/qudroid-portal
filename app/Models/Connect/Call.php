<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    protected $table = 'connect_calls';

    protected $fillable = ['conversation_id', 'started_by', 'kind', 'is_huddle', 'started_at', 'ended_at', 'peak_people', 'transcribing_by', 'note_id'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime', 'is_huddle' => 'boolean'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function starter(): BelongsTo
    {
        return $this->belongsTo(User::class, 'started_by');
    }

    public function people(): HasMany
    {
        return $this->hasMany(CallPerson::class);
    }

    public function recordings(): HasMany
    {
        return $this->hasMany(CallRecording::class);
    }

    public function transcript(): HasMany
    {
        return $this->hasMany(TranscriptLine::class)->orderBy('id');
    }

    public function note(): BelongsTo
    {
        return $this->belongsTo(Note::class);
    }

    public function isLive(): bool
    {
        return $this->ended_at === null;
    }

    public function durationLabel(): string
    {
        $seconds = (int) $this->started_at->diffInSeconds($this->ended_at ?? now(), true);
        if ($seconds < 60) return $seconds . ' sec';
        if ($seconds < 3600) return intdiv($seconds, 60) . ' min';

        return intdiv($seconds, 3600) . ' h ' . intdiv($seconds % 3600, 60) . ' min';
    }
}
