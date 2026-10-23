<?php

namespace App\Models\Checkin;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One person's check-in (and check-out) at an event. */
class Record extends Model
{
    protected $table = 'checkin_records';

    public const METHODS = ['screen' => 'Scanned the door QR', 'badge' => 'Badge scanned', 'manual' => 'Marked by hand'];

    protected $fillable = ['event_id', 'user_id', 'guest_id', 'status', 'method', 'checked_in_at', 'checked_out_at', 'scanned_by', 'lat', 'lng', 'distance_m', 'note'];

    protected $casts = ['checked_in_at' => 'datetime', 'checked_out_at' => 'datetime', 'lat' => 'float', 'lng' => 'float'];

    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class, 'event_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function guest(): BelongsTo
    {
        return $this->belongsTo(Guest::class, 'guest_id');
    }

    public function scanner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'scanned_by');
    }

    public function personName(): string
    {
        return $this->user?->name ?? $this->guest?->name ?? 'Unknown';
    }
}
