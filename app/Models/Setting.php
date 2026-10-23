<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One portal-wide setting. Read and write through App\Services\Settings\Settings, which handles encryption. */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by'];

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
