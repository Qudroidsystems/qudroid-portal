<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Participant extends Model
{
    protected $table = 'connect_participants';

    protected $fillable = ['conversation_id', 'user_id', 'role', 'last_read_message_id', 'muted', 'left_at'];

    protected $casts = ['muted' => 'boolean', 'left_at' => 'datetime'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
