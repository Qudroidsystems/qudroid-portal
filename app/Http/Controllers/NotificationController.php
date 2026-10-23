<?php

namespace App\Http\Controllers;

use App\Services\Messaging\NotificationActions;
use App\Services\Messaging\NotificationPresenter;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

/** The notification centre (the bell's panel), the notifications page and their settings. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('notifications'), 404);
        $user = $request->user();
        $filter = $request->get('filter') ?: $request->get('kind');
        $page = NotificationPresenter::query($user, $filter)->paginate(30)->withQueryString();

        return view('notifications.index', [
            'pagetitle'     => 'Notifications',
            'page'          => $page,
            'items'         => collect($page->items())->map(fn ($n) => NotificationPresenter::item($n)),
            'unread'        => $user->unreadNotifications()->count(),
            'groups'        => PortalNotifier::GROUPS,
            'filters'       => NotificationPresenter::FILTERS,
            'filter'        => $filter ?: 'all',
            'settings'      => NotificationPresenter::settings($user),
        ]);
    }

    /** Which kinds to get, quiet hours and the daily summary. */
    public function mute(Request $request)
    {
        $data = $request->validate([
            'on' => ['array'], 'on.*' => ['string'],
            'quiet_from' => ['nullable', 'date_format:H:i'], 'quiet_to' => ['nullable', 'date_format:H:i', 'required_with:quiet_from'],
            'digest' => ['nullable', 'boolean'],
        ]);
        $keys = array_keys(PortalNotifier::GROUPS);
        $on = array_intersect((array) ($data['on'] ?? []), $keys);
        $quiet = $request->boolean('quiet') && !empty($data['quiet_from']) && !empty($data['quiet_to']);
        $request->user()->forceFill([
            'muted_notifications' => array_values(array_diff($keys, $on)),
            'notify_quiet_from' => $quiet ? $data['quiet_from'] : null,
            'notify_quiet_to' => $quiet ? $data['quiet_to'] : null,
            'notify_digest' => $request->boolean('digest'),
        ])->save();

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'settings' => NotificationPresenter::settings($request->user()->fresh())])
            : back()->with('success', 'Notification settings saved.');
    }

    /** Remove notifications you've already read. */
    public function clearRead(Request $request)
    {
        $n = $request->user()->readNotifications()->delete();

        return $request->expectsJson() ? response()->json(['ok' => true, 'removed' => $n])
            : back()->with('success', $n ? "Removed {$n} read notifications." : 'Nothing to remove.');
    }

    /**
     * The notification centre: stacked by conversation or item, newest first.
     * `latest` lets the page show a banner (and play a sound) for something new.
     */
    public function feed(Request $request)
    {
        if (!Schema::hasTable('notifications')) return response()->json(['unread' => 0, 'items' => []]);
        $user = $request->user();
        $filter = $request->get('filter', 'all');
        $items = NotificationPresenter::query($user, $filter)->limit(60)->get()->map(fn ($n) => NotificationPresenter::item($n));
        $latest = $user->unreadNotifications()->first();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'quiet' => PortalNotifier::inQuietHours($user),
            'latest' => $latest ? NotificationPresenter::item($latest) : null,
            'items' => NotificationPresenter::stacks($items),
        ]);
    }

    /** A button on a notification: Approve, Reply, Going… */
    public function act(Request $request, string $id, string $action, NotificationActions $actions)
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $request->validate(['input' => ['nullable', 'string', 'max:4000']]);
        try {
            $done = $actions->run($n, $request->user(), $action, $request->input('input'));
        } catch (ValidationException $e) {
            if (!$request->expectsJson()) throw $e;

            return response()->json(['ok' => false, 'message' => collect($e->errors())->flatten()->first()], 422);
        }

        return $request->expectsJson()
            ? response()->json(['ok' => true, 'done' => $done, 'item' => NotificationPresenter::item($n->fresh()), 'unread' => $request->user()->unreadNotifications()->count()])
            : back()->with('success', $done . '.');
    }

    /** Mark read (or unread with ?unread=1); `ids[]` marks a whole stack. */
    public function read(Request $request, string $id)
    {
        $ids = array_filter(array_merge([$id], (array) $request->input('ids', [])), 'is_string');
        $q = $request->user()->notifications()->whereIn('id', $ids);
        $q->update(['read_at' => $request->boolean('unread') ? null : now()]);

        return response()->json(['ok' => true, 'unread' => $request->user()->unreadNotifications()->count()]);
    }

    /** Clear one notification (or a whole stack with `ids[]`). */
    public function destroy(Request $request, string $id)
    {
        $ids = array_filter(array_merge([$id], (array) $request->input('ids', [])), 'is_string');
        $request->user()->notifications()->whereIn('id', $ids)->delete();

        return $request->expectsJson() ? response()->json(['ok' => true, 'unread' => $request->user()->unreadNotifications()->count()])
            : back();
    }

    public function open(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->firstOrFail();
        $n->markAsRead();
        $url = $n->data['url'] ?? null;
        // Only follow links inside the portal.
        $host = $url ? parse_url($url, PHP_URL_HOST) : null;
        if ($url && (!$host || $host === $request->getHost())) {
            return redirect()->to($url);
        }
        return redirect()->route('notifications.index');
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return $request->expectsJson() ? response()->json(['ok' => true]) : back()->with('success', 'All notifications marked as read.');
    }
}
