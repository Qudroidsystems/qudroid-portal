<?php

namespace App\Models\Checkin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/** Someone registered for an event (often from outside the company), with a ticket code. */
class Guest extends Model
{
    protected $table = 'checkin_guests';

    protected $fillable = ['event_id', 'user_id', 'name', 'email', 'phone', 'organisation', 'code', 'cancelled_at'];

    protected $casts = ['cancelled_at' => 'datetime'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function record(): HasOne
    {
        return $this->hasOne(Record::class, 'guest_id');
    }
}
