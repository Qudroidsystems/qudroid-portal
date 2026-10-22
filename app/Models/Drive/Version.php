<?php

namespace App\Models\Drive;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Version extends Model
{
    protected $table = 'drive_versions';

    public const UPDATED_AT = null;

    protected $fillable = ['file_id', 'version', 'path', 'mime', 'size', 'uploaded_by'];

    public function file(): BelongsTo
    {
        return $this->belongsTo(File::class);
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
