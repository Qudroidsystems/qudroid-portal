<?php

namespace App\Http\Controllers;

use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** The bell: list, open (marks read) and mark all as read. */
class NotificationController extends Controller
{
    public function index(Request $request)
    {
        abort_unless(Schema::hasTable('notifications'), 404);
        $user = $request->user();
        $q = $user->notifications();
        if ($request->get('filter') === 'unread') $q = $user->unreadNotifications();
        $kind = $request->get('kind');
        if ($kind && isset(PortalNotifier::GROUPS[$kind])) {
            $types = PortalNotifier::GROUPS[$kind][2];
            $q->where(fn ($w) => collect($types)->each(fn ($t) => $w->orWhere('data', 'like', '%"type":"' . $t . '"%')));
        }

        return view('notifications.index', [
            'pagetitle'     => 'Notifications',
            'notifications' => $q->paginate(25)->withQueryString(),
            'unread'        => $user->unreadNotifications()->count(),
            'groups'        => PortalNotifier::GROUPS,
            'kind'          => $kind,
            'muted'         => (array) $user->muted_notifications,
        ]);
    }

    /** Switch kinds of notification on or off for yourself. */
    public function mute(Request $request)
    {
        $keys = array_keys(PortalNotifier::GROUPS);
        $on = array_intersect((array) $request->input('on', []), $keys);
        $request->user()->forceFill(['muted_notifications' => array_values(array_diff($keys, $on))])->save();

        return back()->with('success', 'Notification settings saved.');
    }

    /** Remove notifications you've already read. */
    public function clearRead(Request $request)
    {
        $n = $request->user()->readNotifications()->delete();

        return back()->with('success', $n ? "Removed {$n} read notifications." : 'Nothing to remove.');
    }

    /** Latest items for the bell dropdown. */
    public function feed(Request $request)
    {
        if (!Schema::hasTable('notifications')) return response()->json(['unread' => 0, 'items' => []]);
        $user = $request->user();

        return response()->json([
            'unread' => $user->unreadNotifications()->count(),
            'latest' => ($l = $user->unreadNotifications()->first()) ? ['id' => $l->id, 'type' => $l->data['type'] ?? 'system'] : null,
            'items'  => $user->notifications()->limit(8)->get()->map(fn ($n) => [
                'id'    => $n->id,
                'title' => $n->data['title'] ?? 'Notification',
                'body'  => mb_strimwidth($n->data['body'] ?? '', 0, 140, '…'),
                'icon'  => $n->data['icon'] ?? 'ri-notification-3-line',
                'read'  => (bool) $n->read_at,
                'time'  => $n->created_at->diffForHumans(),
                'url'   => route('notifications.open', $n->id),
            ]),
        ]);
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
