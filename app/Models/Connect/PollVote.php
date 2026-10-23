<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PollVote extends Model
{
    protected $table = 'connect_poll_votes';

    protected $fillable = ['message_id', 'user_id', 'option'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
