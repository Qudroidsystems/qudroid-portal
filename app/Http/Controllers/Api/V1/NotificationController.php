<?php

namespace App\Http\Controllers\Api\V1;

use App\Services\Messaging\NotificationActions;
use App\Services\Messaging\NotificationPresenter;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** The notification centre for the app: list, stacks, buttons, settings. Same shapes as the web. */
class NotificationController extends ApiController
{
    /** ?filter=all|unread|mentions|approvals|{group}  ?stacked=1 groups by thread (newest first) */
    public function index(Request $request)
    {
        if (!Schema::hasTable('notifications')) return $this->ok([], null, 200, ['unread' => 0]);

        $user = $request->user();
        $q = NotificationPresenter::query($user, $request->get('filter'));
        if ($request->boolean('stacked')) {
            $items = NotificationPresenter::stacks($q->limit(100)->get()->map(fn ($n) => $this->item($n)));

            return $this->ok($items, null, 200, ['unread' => $user->unreadNotifications()->count(), 'quiet' => PortalNotifier::inQuietHours($user)]);
        }
        $page = $q->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        $res = $this->paged($page, fn ($n) => $this->item($n));
        $body = $res->getData(true);
        $body['meta']['unread'] = $user->unreadNotifications()->count();
        $body['meta']['quiet'] = PortalNotifier::inQuietHours($user);

        return response()->json($body);
    }

    public function unreadCount(Request $request)
    {
        $count = Schema::hasTable('notifications') ? $request->user()->unreadNotifications()->count() : 0;
        return $this->ok(['unread' => $count]);
    }

    /** Mark read, or unread with {"unread": true}; {"ids": [...]} does a whole stack. */
    public function read(Request $request, string $id)
    {
        $ids = array_filter(array_merge([$id], (array) $request->input('ids', [])), 'is_string');
        $found = $request->user()->notifications()->whereIn('id', $ids)->update(['read_at' => $request->boolean('unread') ? null : now()]);
        if (!$found) return $this->fail('Notification not found.', 404, 'not_found');

        return $this->ok($this->item($request->user()->notifications()->where('id', $id)->first() ?? $request->user()->notifications()->whereIn('id', $ids)->first()),
            null, 200, ['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return $this->ok(null, 'All notifications marked as read.', 200, ['unread' => 0]);
    }

    /** Clear one, or a stack with {"ids": [...]}. */
    public function destroy(Request $request, string $id)
    {
        $ids = array_filter(array_merge([$id], (array) $request->input('ids', [])), 'is_string');
        $request->user()->notifications()->whereIn('id', $ids)->delete();

        return $this->ok(null, 'Cleared.', 200, ['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function clearRead(Request $request)
    {
        $n = $request->user()->readNotifications()->delete();

        return $this->ok(['removed' => $n], $n ? "Removed {$n} read notifications." : 'Nothing to remove.');
    }

    /** A button: POST notifications/{id}/actions/{action} {"input": "…"} */
    public function act(Request $request, string $id, string $action, NotificationActions $actions)
    {
        $n = $request->user()->notifications()->where('id', $id)->first();
        if (!$n) return $this->fail('Notification not found.', 404, 'not_found');
        $request->validate(['input' => ['nullable', 'string', 'max:4000']]);
        try {
            $done = $actions->run($n, $request->user(), $action, $request->input('input'));
        } catch (ValidationException $e) {
            return $this->fail(collect($e->errors())->flatten()->first(), 422, 'action_failed');
        }

        return $this->ok($this->item($n->fresh()), $done . '.', 200, ['unread' => $request->user()->unreadNotifications()->count()]);
    }

    public function settings(Request $request)
    {
        return $this->ok(NotificationPresenter::settings($request->user()));
    }

    /** {"groups": {"messages": true, …}, "quiet_from": "22:00"|null, "quiet_to": "07:00"|null, "digest": bool} — send only what changes. */
    public function updateSettings(Request $request)
    {
        $d = $request->validate([
            'groups' => ['sometimes', 'array'], 'groups.*' => ['boolean'],
            'quiet_from' => ['sometimes', 'nullable', 'date_format:H:i'], 'quiet_to' => ['sometimes', 'nullable', 'date_format:H:i'],
            'digest' => ['sometimes', 'boolean'],
        ]);
        $user = $request->user();
        $fill = [];
        if (isset($d['groups'])) {
            $muted = collect((array) $user->muted_notifications);
            foreach ($d['groups'] as $key => $on) {
                if (!isset(PortalNotifier::GROUPS[$key])) continue;
                $muted = $on ? $muted->reject(fn ($k) => $k === $key) : $muted->push($key);
            }
            $fill['muted_notifications'] = $muted->unique()->values()->all();
        }
        if (array_key_exists('quiet_from', $d) || array_key_exists('quiet_to', $d)) {
            $from = $d['quiet_from'] ?? null;
            $to = $d['quiet_to'] ?? null;
            if (($from === null) !== ($to === null)) return $this->fail('Give both a start and an end for quiet hours, or neither.', 422, 'validation');
            $fill += ['notify_quiet_from' => $from, 'notify_quiet_to' => $to];
        }
        if (array_key_exists('digest', $d)) $fill['notify_digest'] = $d['digest'];
        $user->forceFill($fill)->save();

        return $this->ok(NotificationPresenter::settings($user->fresh()), 'Saved.');
    }

    protected function item($n): array
    {
        $item = NotificationPresenter::item($n);
        unset($item['open_url']);

        return $item + ['data' => (array) $n->data];
    }
}
