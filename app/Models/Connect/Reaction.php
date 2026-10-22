<?php

namespace App\Models\Connect;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    public const ALLOWED = ['👍', '❤️', '😂', '🎉', '😮', '🙏'];

    protected $table = 'connect_reactions';

    protected $fillable = ['message_id', 'user_id', 'emoji'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }
}
