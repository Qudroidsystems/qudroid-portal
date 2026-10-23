<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledMessage extends Model
{
    protected $table = 'connect_scheduled';

    protected $fillable = ['conversation_id', 'user_id', 'body', 'reply_to_id', 'thread_id', 'send_at', 'sent_at', 'message_id', 'failed_reason'];

    protected $casts = ['send_at' => 'datetime', 'sent_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function scopePending($q)
    {
        return $q->whereNull('sent_at')->whereNull('failed_reason');
    }
}
