<?php

namespace App\Models\Academy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/** One lesson: text (Markdown), an embedded video, a file to download, or a link. */
class Lesson extends Model
{
    public const TYPES = ['text' => 'Reading', 'video' => 'Video', 'file' => 'File', 'link' => 'Link'];

    public const ICONS = ['text' => 'ri-article-line', 'video' => 'ri-play-circle-line', 'file' => 'ri-file-download-line', 'link' => 'ri-external-link-line'];

    protected $table = 'academy_lessons';

    protected $fillable = [
        'course_id', 'section_id', 'title', 'type', 'body', 'video_url', 'external_url', 'file_path',
        'file_name', 'file_size', 'duration_minutes', 'position', 'is_preview', 'is_published', 'created_by',
    ];

    protected $casts = [
        'is_preview'       => 'boolean',
        'is_published'     => 'boolean',
        'duration_minutes' => 'integer',
        'file_size'        => 'integer',
    ];

    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class, 'course_id')->withTrashed();
    }

    public function section(): BelongsTo
    {
        return $this->belongsTo(Section::class, 'section_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Lesson text as HTML. Raw HTML in the Markdown is stripped. */
    public function bodyHtml(): string
    {
        return $this->body ? Str::markdown($this->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]) : '';
    }

    /** Embed URL for YouTube and Vimeo links; null for anything else (shown as a plain link). */
    public function embedUrl(): ?string
    {
        $url = (string) $this->video_url;
        if (preg_match('~^https?://(?:www\.|m\.)?(?:youtube\.com/(?:watch\?(?:.*&)?v=|embed/|shorts/|live/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return 'https://www.youtube-nocookie.com/embed/' . $m[1] . '?rel=0';
        }
        if (preg_match('~^https?://(?:www\.|player\.)?vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
            return 'https://player.vimeo.com/video/' . $m[1];
        }

        return null;
    }

    public function getTypeLabelAttribute(): string
    {
        return self::TYPES[$this->type] ?? ucfirst((string) $this->type);
    }

    public function getIconAttribute(): string
    {
        return self::ICONS[$this->type] ?? 'ri-file-line';
    }
}
