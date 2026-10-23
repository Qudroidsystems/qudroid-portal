<?php

namespace App\Models\Mail;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MailAttachment extends Model
{
    protected $fillable = ['mail_message_id', 'filename', 'mime', 'size', 'content_id', 'inline', 'path'];

    protected $casts = ['inline' => 'boolean'];

    public function message(): BelongsTo
    {
        return $this->belongsTo(MailMessage::class, 'mail_message_id');
    }

    public function sizeLabel(): string
    {
        $b = $this->size;

        return $b >= 1048576 ? round($b / 1048576, 1) . ' MB' : ($b >= 1024 ? round($b / 1024) . ' KB' : $b . ' B');
    }
}
