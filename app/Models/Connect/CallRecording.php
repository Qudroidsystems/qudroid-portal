<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallRecording extends Model
{
    protected $table = 'connect_call_recordings';

    protected $fillable = ['call_id', 'user_id', 'status', 'path', 'mime', 'size', 'chunks', 'duration_seconds', 'started_at', 'ended_at'];

    protected $casts = ['started_at' => 'datetime', 'ended_at' => 'datetime', 'size' => 'integer', 'chunks' => 'integer', 'duration_seconds' => 'integer'];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isAudio(): bool
    {
        return str_starts_with($this->mime, 'audio/');
    }

    public function durationLabel(): string
    {
        $s = (int) ($this->duration_seconds ?? ($this->ended_at ? $this->started_at->diffInSeconds($this->ended_at, true) : 0));
        if ($s < 60) return $s . ' sec';
        if ($s < 3600) return intdiv($s, 60) . ' min ' . ($s % 60) . ' sec';

        return intdiv($s, 3600) . ' h ' . intdiv($s % 3600, 60) . ' min';
    }

    public function sizeLabel(): string
    {
        return $this->size >= 1048576 ? round($this->size / 1048576, 1) . ' MB' : max(1, (int) round($this->size / 1024)) . ' KB';
    }
}
