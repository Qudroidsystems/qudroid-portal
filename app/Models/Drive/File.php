<?php

namespace App\Models\Drive;

use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class File extends Model
{
    protected $table = 'drive_files';

    protected $fillable = ['name', 'folder_id', 'owner_id', 'project_id', 'path', 'mime', 'size', 'version', 'description',
        'updated_by', 'trashed_at', 'trashed_by'];

    protected $casts = ['trashed_at' => 'datetime'];

    public const TYPE = 'file';

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(Version::class)->orderByDesc('version');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('trashed_at');
    }

    public function extension(): string
    {
        return strtolower(pathinfo($this->name, PATHINFO_EXTENSION));
    }

    /** How the file can be shown in the browser: image, pdf, video, audio, text, or null (download only). */
    public function previewKind(): ?string
    {
        $mime = (string) $this->mime;
        // SVG and HTML can carry scripts, so they are never shown inline.
        if (in_array($this->extension(), ['svg', 'svgz', 'html', 'htm', 'xhtml', 'xml'], true) || str_contains($mime, 'svg') || str_contains($mime, 'html')) return null;

        return match (true) {
            str_starts_with($mime, 'image/') => 'image',
            $mime === 'application/pdf' => 'pdf',
            str_starts_with($mime, 'video/') => 'video',
            str_starts_with($mime, 'audio/') => 'audio',
            str_starts_with($mime, 'text/') || in_array($this->extension(), ['txt', 'md', 'csv', 'log', 'json'], true) => 'text',
            default => null,
        };
    }

    public function icon(): string
    {
        return self::iconFor($this->mime, $this->extension());
    }

    public static function iconFor(?string $mime, string $ext = ''): string
    {
        $mime = (string) $mime;

        return match (true) {
            str_starts_with($mime, 'image/') => 'ri-image-line text-info',
            $mime === 'application/pdf' => 'ri-file-pdf-2-line text-danger',
            str_starts_with($mime, 'video/') => 'ri-film-line text-primary',
            str_starts_with($mime, 'audio/') => 'ri-music-2-line text-primary',
            in_array($ext, ['doc', 'docx', 'odt', 'rtf'], true) => 'ri-file-word-2-line text-primary',
            in_array($ext, ['xls', 'xlsx', 'ods', 'csv'], true) => 'ri-file-excel-2-line text-success',
            in_array($ext, ['ppt', 'pptx', 'odp', 'key'], true) => 'ri-file-ppt-2-line text-warning',
            in_array($ext, ['zip', 'rar', '7z', 'tar', 'gz'], true) => 'ri-file-zip-line text-secondary',
            in_array($ext, ['php', 'js', 'ts', 'py', 'java', 'dart', 'json', 'sql', 'html', 'css'], true) => 'ri-file-code-line text-secondary',
            str_starts_with($mime, 'text/') => 'ri-file-text-line text-secondary',
            default => 'ri-file-3-line text-muted',
        };
    }

    public static function humanSize(int|float|null $bytes): string
    {
        $bytes = (float) $bytes;
        foreach (['B', 'KB', 'MB', 'GB'] as $unit) {
            if ($bytes < 1024 || $unit === 'GB') return ($unit === 'B' ? (int) $bytes : round($bytes, 1)) . ' ' . $unit;
            $bytes /= 1024;
        }

        return '';
    }
}
