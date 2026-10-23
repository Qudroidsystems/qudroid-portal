<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reminder extends Model
{
    protected $table = 'connect_reminders';

    protected $fillable = ['user_id', 'message_id', 'note', 'remind_at', 'reminded_at', 'done_at'];

    protected $casts = ['remind_at' => 'datetime', 'reminded_at' => 'datetime', 'done_at' => 'datetime'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function scopeOpen($q)
    {
        return $q->whereNull('done_at');
    }
}
