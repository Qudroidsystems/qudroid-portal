<?php

namespace App\Models\Connect;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Reaction extends Model
{
    /** The quick row on every message. */
    public const QUICK = ['👍', '❤️', '😂', '🎉', '😮', '🙏'];

    /** The emoji picker, used for reactions and for typing into a message. */
    public const PICKER = [
        'Smileys' => ['😀', '😃', '😄', '😁', '😆', '😅', '🤣', '😂', '🙂', '😉', '😊', '😇', '🥰', '😍', '🤩', '😘', '😋', '😜', '🤪', '🤗', '🤭', '🤫', '🤔', '🤐',
            '😐', '😑', '😶', '😏', '😒', '🙄', '😬', '😌', '😔', '😪', '😴', '😷', '🤒', '🥵', '🥶', '😵', '🤯', '🥳', '😎', '🤓', '🧐', '😕', '😟', '😮',
            '😲', '😳', '🥺', '😢', '😭', '😱', '😖', '😤', '😡', '🤬', '💀', '🤡', '👻', '🙈', '🙉', '🙊'],
        'Gestures' => ['👍', '👎', '👌', '✌️', '🤞', '🤟', '🤙', '👈', '👉', '👆', '👇', '☝️', '✋', '🖐️', '👋', '👏', '🙌', '👐', '🤲', '🤝', '🙏', '✍️', '💪', '🫡',
            '🫶', '👀', '🧠', '🏃', '💃', '🕺', '🤷', '🤦', '🙋', '🙆', '🙅', '🫠'],
        'Hearts' => ['❤️', '🧡', '💛', '💚', '💙', '💜', '🖤', '🤍', '🤎', '💔', '❣️', '💕', '💞', '💓', '💗', '💖', '💘', '💯', '✨', '⭐', '🌟', '🔥', '💥', '💫'],
        'Work' => ['✅', '☑️', '✔️', '❌', '❗', '❓', '⚠️', '🚀', '🎯', '📌', '📎', '📝', '📅', '📊', '📈', '📉', '💡', '🔧', '🛠️', '💻', '📱', '📞', '📧', '📢',
            '🔔', '🔒', '🔑', '💰', '💵', '🧾', '🏆', '🥇', '🎓', '📚', '⏰', '⏳'],
        'Fun' => ['🎉', '🎊', '🎈', '🎁', '🍾', '🥂', '🍕', '🍔', '🍟', '🌮', '🍰', '🎂', '🍫', '🍩', '☕', '🍵', '🍺', '⚽', '🏀', '🎮', '🎵', '🎶', '🎬', '📸',
            '🌍', '☀️', '🌈', '🌧️', '❄️', '🌙', '🌹', '🌻', '🐶', '🐱', '🦁', '🐐'],
    ];

    protected $table = 'connect_reactions';

    protected $fillable = ['message_id', 'user_id', 'emoji'];

    /** Reactions show who reacted, so the name always comes along. */
    protected $with = ['user:id,name'];

    public static function allowed(string $emoji): bool
    {
        static $all;
        $all ??= array_flip(array_merge(self::QUICK, ...array_values(self::PICKER)));

        return isset($all[$emoji]);
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(Message::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
