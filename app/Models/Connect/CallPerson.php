<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallPerson extends Model
{
    protected $table = 'connect_call_people';

    protected $fillable = ['call_id', 'user_id', 'joined_at', 'left_at', 'declined_at', 'seen_at', 'state'];

    protected $casts = ['joined_at' => 'datetime', 'left_at' => 'datetime', 'declined_at' => 'datetime', 'seen_at' => 'datetime', 'state' => 'array'];

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
