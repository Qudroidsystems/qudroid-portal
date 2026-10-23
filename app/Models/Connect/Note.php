<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Note extends Model
{
    protected $table = 'connect_notes';

    protected $fillable = ['conversation_id', 'title', 'body', 'version', 'pinned', 'created_by', 'updated_by'];

    protected $casts = ['pinned' => 'boolean', 'version' => 'integer'];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function editor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NoteRevision::class)->orderByDesc('version');
    }

    /** The note as safe HTML: Markdown with raw HTML removed and unsafe links dropped. */
    public static function render(?string $markdown): string
    {
        return (string) Str::markdown((string) $markdown, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
    }

    public function html(): string
    {
        return self::render($this->body);
    }

    public function excerpt(int $limit = 120): string
    {
        $text = preg_replace('/[#>*_`\[\]()~-]+/', ' ', (string) $this->body);

        return Str::limit(trim(preg_replace('/\s+/', ' ', $text)), $limit);
    }
}
