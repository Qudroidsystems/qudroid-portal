<?php

namespace App\Models\Meetings;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Attendee extends Model
{
    public const RESPONSES = ['pending' => 'Not answered', 'accepted' => 'Going', 'tentative' => 'Maybe', 'declined' => 'Not going'];

    protected $table = 'meeting_attendees';

    protected $fillable = ['meeting_id', 'user_id', 'response', 'note'];

    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
