<?php

namespace App\Models\Connect;

use App\Models\Projects\Project;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Conversation extends Model
{
    public const TYPES = ['direct' => 'Direct message', 'group' => 'Group', 'channel' => 'Channel'];

    protected $table = 'connect_conversations';

    protected $fillable = ['type', 'name', 'description', 'direct_key', 'project_id', 'created_by', 'last_message_id', 'last_message_at', 'archived_at'];

    protected $casts = ['last_message_at' => 'datetime', 'archived_at' => 'datetime'];

    public function participants(): HasMany
    {
        return $this->hasMany(Participant::class);
    }

    /** People currently in the conversation. */
    public function members(): HasMany
    {
        return $this->hasMany(Participant::class)->whereNull('left_at');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }

    public function lastMessage(): BelongsTo
    {
        return $this->belongsTo(Message::class, 'last_message_id');
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function isDirect(): bool
    {
        return $this->type === 'direct';
    }

    /** For a direct chat, the other person. */
    public function otherUser(User $me): ?User
    {
        $p = $this->relationLoaded('members')
            ? $this->members->firstWhere('user_id', '!=', $me->id)
            : $this->members()->where('user_id', '!=', $me->id)->with('user')->first();

        return $p?->user;
    }

    /** What to call this conversation for this person. */
    public function titleFor(User $me): string
    {
        if ($this->isDirect()) {
            return $this->otherUser($me)?->name ?? 'Just you';
        }

        return ($this->type === 'channel' ? '#' : '') . $this->name;
    }

    public static function directKey(int $a, int $b): string
    {
        return min($a, $b) . ':' . max($a, $b);
    }
}
