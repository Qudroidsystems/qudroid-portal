<?php

namespace App\Services\Connect;

use App\Models\Connect\Call;
use App\Models\Connect\CallPerson;
use App\Models\Connect\Conversation;
use App\Models\Connect\Message;
use App\Models\Connect\Participant;
use App\Models\User;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Voice and video calls in a conversation.
 *
 * Every browser in a call connects straight to every other one (WebRTC
 * "mesh"), so calls suit small teams; the portal only relays the set-up
 * messages (offer, answer, network candidates) and tracks who is in.
 * The call page checks in every second; anyone silent for 30 seconds is
 * counted as gone, and a call with nobody left ends.
 *
 * Direct and group chats ring their members (bell, phone push and an
 * on-screen prompt on any portal page); channels show a "Join" banner only.
 */
class CallService
{
    public const STALE_SECONDS = 30;
    public const RING_SECONDS = 60;

    public function __construct(protected ConnectService $connect) {}

    public function iceServers(): array
    {
        $servers = [];
        $stun = config('qudroid.calls.stun', []);
        if ($stun) $servers[] = ['urls' => array_values($stun)];
        if ($url = config('qudroid.calls.turn_url')) {
            $servers[] = ['urls' => $url, 'username' => (string) config('qudroid.calls.turn_username'), 'credential' => (string) config('qudroid.calls.turn_credential')];
        }

        return $servers;
    }

    public function live(Conversation $c): ?Call
    {
        $call = Call::where('conversation_id', $c->id)->whereNull('ended_at')->latest('id')->first();
        if ($call && $this->sweep($call)) return null;

        return $call;
    }

    public function start(Conversation $c, User $me, string $kind): Call
    {
        $this->check(!$this->connect->participant($c, $me), 'call', $c->type === 'channel' ? 'Join the channel to start a call.' : 'You are not in this conversation.');
        $this->check($c->archived_at !== null, 'call', 'This conversation is archived.');
        if ($existing = $this->live($c)) return $existing;

        $call = DB::transaction(function () use ($c, $me, $kind) {
            $call = Call::create(['conversation_id' => $c->id, 'started_by' => $me->id, 'kind' => $kind === 'audio' ? 'audio' : 'video', 'started_at' => now()]);
            $this->announce($c, $me->name . ' started a ' . ($call->kind === 'audio' ? 'voice' : 'video') . ' call');

            return $call;
        });

        if ($c->type !== 'channel') {
            $others = Participant::where('conversation_id', $c->id)->whereNull('left_at')->where('user_id', '!=', $me->id)->pluck('user_id')->all();
            PortalNotifier::toUsers($others, ($call->kind === 'audio' ? 'Voice' : 'Video') . ' call from ' . $me->name,
                $c->isDirect() ? 'Tap to join.' : 'In ' . $c->titleFor($me) . '. Tap to join.', route('connect.calls.show', $call), 'message', 'connect-call:' . $call->id);
        }

        return $call;
    }

    /** Join (or rejoin) a call. Returns who else is in it right now. */
    public function join(Call $call, User $me): Collection
    {
        $this->assertCanJoin($call, $me);
        $here = $this->present($call)->reject(fn ($p) => $p->user_id === $me->id);
        $max = max(2, (int) config('qudroid.calls.max_people', 8));
        $this->check($here->count() >= $max, 'call', 'This call is full (' . $max . ' people).');

        DB::transaction(function () use ($call, $me, $here) {
            CallPerson::updateOrCreate(['call_id' => $call->id, 'user_id' => $me->id],
                ['joined_at' => now(), 'left_at' => null, 'declined_at' => null, 'seen_at' => now()]);
            // Old set-up messages to me are from an earlier attempt.
            DB::table('connect_call_signals')->where('call_id', $call->id)->where('to_user_id', $me->id)->delete();
            if ($here->count() + 1 > $call->peak_people) $call->update(['peak_people' => $here->count() + 1]);
        });

        return $here->values();
    }

    /** Pass a set-up message to someone else in the call. */
    public function signal(Call $call, User $me, int $to, string $type, ?string $payload): void
    {
        $this->check(!in_array($type, ['offer', 'answer', 'ice', 'bye'], true), 'type', 'Unknown signal.');
        $this->check(strlen((string) $payload) > 200_000, 'payload', 'That signal is too large.');
        $this->check(!$this->isIn($call, $me->id), 'call', 'Join the call first.');
        $this->check(!CallPerson::where('call_id', $call->id)->where('user_id', $to)->whereNotNull('joined_at')->whereNull('left_at')->exists(), 'to', 'They are not in the call.');

        DB::table('connect_call_signals')->insert([
            'call_id' => $call->id, 'from_user_id' => $me->id, 'to_user_id' => $to, 'type' => $type, 'payload' => $payload, 'created_at' => now(),
        ]);
    }

    /**
     * The call page's check-in: records that the person is still here (and whether their mic,
     * camera and screen are on), and returns new signals for them and who is in the call.
     */
    public function checkIn(Call $call, User $me, int $after, array $state = []): array
    {
        $person = CallPerson::where('call_id', $call->id)->where('user_id', $me->id)->first();
        if ($person && $person->joined_at && !$person->left_at && !$call->ended_at) {
            $person->update(['seen_at' => now(), 'state' => array_intersect_key(array_map('boolval', $state), array_flip(['mic', 'cam', 'screen']))]);
        }
        $this->sweep($call);
        $call->refresh();

        $signals = DB::table('connect_call_signals')->where('call_id', $call->id)->where('to_user_id', $me->id)->where('id', '>', $after)
            ->orderBy('id')->limit(100)->get(['id', 'from_user_id as from', 'type', 'payload']);

        return [
            'ended' => $call->ended_at !== null,
            'in' => $this->isIn($call, $me->id),
            'people' => $this->present($call)->map(fn ($p) => [
                'id' => $p->user_id, 'name' => $p->user?->name, 'avatar' => $p->user?->avatar_url,
                'mic' => (bool) ($p->state['mic'] ?? true), 'cam' => (bool) ($p->state['cam'] ?? false), 'screen' => (bool) ($p->state['screen'] ?? false),
            ])->values(),
            'signals' => $signals,
            'duration' => $call->durationLabel(),
        ];
    }

    public function leave(Call $call, User $me): void
    {
        CallPerson::where('call_id', $call->id)->where('user_id', $me->id)->whereNull('left_at')->update(['left_at' => now()]);
        DB::table('connect_call_signals')->where('call_id', $call->id)->where(fn ($q) => $q->where('to_user_id', $me->id)->orWhere('from_user_id', $me->id))->delete();
        $this->sweep($call);
    }

    /** Say no to a ringing call. In a direct chat that ends the call for the caller too. */
    public function decline(Call $call, User $me): void
    {
        $this->assertCanJoin($call, $me);
        CallPerson::updateOrCreate(['call_id' => $call->id, 'user_id' => $me->id], ['declined_at' => now()]);
        if ($call->conversation->isDirect() && !$this->isIn($call, $me->id)) {
            $this->end($call, $me->name . ' declined the call');
        }
    }

    /** Calls ringing for the person: just started, in their direct or group chats, not yet answered. */
    public function ringing(User $me): Collection
    {
        $conversationIds = Participant::where('user_id', $me->id)->whereNull('left_at')->pluck('conversation_id');
        $answered = CallPerson::where('user_id', $me->id)->pluck('call_id');
        // Tidy calls that stopped ringing and may have nobody left in them.
        Call::whereNull('ended_at')->whereIn('conversation_id', $conversationIds)->where('started_at', '<', now()->subSeconds(self::RING_SECONDS))
            ->limit(20)->get()->each(fn ($call) => $this->sweep($call));

        return Call::with('conversation', 'starter')->whereNull('ended_at')->whereIn('conversation_id', $conversationIds)
            ->where('started_by', '!=', $me->id)->whereNotIn('id', $answered)
            ->where('started_at', '>=', now()->subSeconds(self::RING_SECONDS))
            ->whereHas('conversation', fn ($q) => $q->where('type', '!=', 'channel'))
            ->latest('id')->get()
            ->reject(fn ($call) => $this->sweep($call))->values();
    }

    /** The person's calls, newest first. */
    public function history(User $me, int $limit = 50): Collection
    {
        $conversationIds = Participant::where('user_id', $me->id)->whereNull('left_at')->pluck('conversation_id');

        $calls = Call::with('conversation', 'starter', 'people.user')->whereIn('conversation_id', $conversationIds)->latest('id')->limit($limit)->get();
        $calls->whereNull('ended_at')->each(fn ($call) => $this->sweep($call) && $call->refresh());

        return $calls;
    }

    public function canJoin(Call $call, User $me): bool
    {
        return $this->connect->participant($call->conversation, $me) !== null;
    }

    /** People in the call right now. */
    public function present(Call $call): Collection
    {
        return CallPerson::with('user')->where('call_id', $call->id)->whereNotNull('joined_at')->whereNull('left_at')
            ->where('seen_at', '>=', now()->subSeconds(self::STALE_SECONDS))->orderBy('joined_at')->get();
    }

    /**
     * Tidy a live call: people silent for too long have left, and a call with nobody in it has
     * ended (a call nobody has answered keeps ringing for a minute). Returns true when it has ended.
     */
    public function sweep(Call $call): bool
    {
        if ($call->ended_at) return true;
        CallPerson::where('call_id', $call->id)->whereNotNull('joined_at')->whereNull('left_at')
            ->where('seen_at', '<', now()->subSeconds(self::STALE_SECONDS))->update(['left_at' => now()]);
        $anyone = CallPerson::where('call_id', $call->id)->whereNotNull('joined_at')->whereNull('left_at')->exists();
        $fresh = $call->started_at->gt(now()->subSeconds(self::RING_SECONDS)) && !CallPerson::where('call_id', $call->id)->whereNotNull('joined_at')->exists();
        if ($anyone || $fresh) return false;

        $this->end($call);

        return true;
    }

    protected function end(Call $call, ?string $why = null): void
    {
        $updated = Call::whereKey($call->id)->whereNull('ended_at')->update(['ended_at' => now(), 'updated_at' => now()]);
        if (!$updated) return;
        $call->refresh();
        CallPerson::where('call_id', $call->id)->whereNull('left_at')->whereNotNull('joined_at')->update(['left_at' => now()]);
        DB::table('connect_call_signals')->where('call_id', $call->id)->delete();

        $answered = CallPerson::where('call_id', $call->id)->whereNotNull('joined_at')->where('user_id', '!=', $call->started_by)->exists();
        $text = $why ?? ($answered ? 'Call ended · ' . $call->durationLabel() : 'Missed ' . ($call->kind === 'audio' ? 'voice' : 'video') . ' call');
        $this->announce($call->conversation, $text);
    }

    protected function announce(Conversation $c, string $text): Message
    {
        $m = Message::create(['conversation_id' => $c->id, 'body' => $text, 'is_system' => true]);
        $c->update(['last_message_id' => $m->id, 'last_message_at' => $m->created_at]);

        return $m;
    }

    protected function isIn(Call $call, int $userId): bool
    {
        return CallPerson::where('call_id', $call->id)->where('user_id', $userId)->whereNotNull('joined_at')->whereNull('left_at')->exists();
    }

    protected function assertCanJoin(Call $call, User $me): void
    {
        $this->check($call->ended_at !== null, 'call', 'This call has ended.');
        $this->check(!$this->canJoin($call, $me), 'call', 'You are not in this conversation.');
    }

    protected function check(bool $failed, string $field, string $message): void
    {
        if ($failed) throw ValidationException::withMessages([$field => $message]);
    }
}
