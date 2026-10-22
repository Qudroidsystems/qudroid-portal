<?php

namespace App\Models\Inventory;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssetEvent extends Model
{
    public const LABELS = ['added' => 'Added to the register', 'assigned' => 'Given to', 'returned' => 'Returned by', 'repair' => 'Sent for repair',
        'repaired' => 'Back from repair', 'retired' => 'Retired', 'lost' => 'Reported lost', 'note' => 'Note'];

    protected $table = 'inv_asset_events';

    protected $fillable = ['asset_id', 'type', 'user_id', 'note', 'created_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function getLabelAttribute(): string
    {
        return self::LABELS[$this->type] ?? ucfirst($this->type);
    }
}
