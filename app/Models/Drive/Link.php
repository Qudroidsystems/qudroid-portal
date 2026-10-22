<?php

namespace App\Models\Drive;

use Illuminate\Database\Eloquent\Model;

class Link extends Model
{
    protected $table = 'drive_links';

    protected $fillable = ['item_type', 'item_id', 'token', 'expires_at', 'downloads', 'created_by', 'revoked_at'];

    protected $casts = ['expires_at' => 'datetime', 'revoked_at' => 'datetime'];

    public function isActive(): bool
    {
        return !$this->revoked_at && (!$this->expires_at || $this->expires_at->isFuture());
    }

    public function item(): ?Model
    {
        return $this->item_type === Folder::TYPE ? Folder::find($this->item_id) : File::find($this->item_id);
    }

    public function url(): string
    {
        return route('drive.public', $this->token);
    }
}
