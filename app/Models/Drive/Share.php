<?php

namespace App\Models\Drive;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Share extends Model
{
    protected $table = 'drive_shares';

    public const ROLES = ['viewer' => 'Can view', 'editor' => 'Can edit'];

    protected $fillable = ['item_type', 'item_id', 'user_id', 'role', 'shared_by'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function sharer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'shared_by');
    }

    public function item(): ?Model
    {
        return $this->item_type === Folder::TYPE ? Folder::find($this->item_id) : File::find($this->item_id);
    }
}
