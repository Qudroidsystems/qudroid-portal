<?php

namespace App\Http\Controllers\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;

/** The in-portal notifications (the web bell), for the app's inbox. */
class NotificationController extends ApiController
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('notifications')) return $this->ok([], null, 200, ['unread' => 0]);

        $user = $request->user();
        $q = $request->get('filter') === 'unread' ? $user->unreadNotifications() : $user->notifications();
        $page = $q->paginate(min(50, max(5, (int) $request->get('per_page', 20))));

        $res = $this->paged($page, fn ($n) => $this->item($n));
        $body = $res->getData(true);
        $body['meta']['unread'] = $user->unreadNotifications()->count();

        return response()->json($body);
    }

    public function unreadCount(Request $request)
    {
        $count = Schema::hasTable('notifications') ? $request->user()->unreadNotifications()->count() : 0;
        return $this->ok(['unread' => $count]);
    }

    public function read(Request $request, string $id)
    {
        $n = $request->user()->notifications()->where('id', $id)->first();
        if (!$n) return $this->fail('Notification not found.', 404, 'not_found');
        $n->markAsRead();

        return $this->ok($this->item($n->fresh()));
    }

    public function readAll(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return $this->ok(null, 'All notifications marked as read.');
    }

    protected function item($n): array
    {
        $data = (array) $n->data;
        return [
            'id'         => $n->id,
            'title'      => $data['title'] ?? 'Notification',
            'body'       => $data['body'] ?? '',
            'category'   => $data['category'] ?? $data['type'] ?? class_basename($n->type),
            'url'        => $data['url'] ?? null,
            'read'       => (bool) $n->read_at,
            'created_at' => optional($n->created_at)->toIso8601String(),
            'data'       => $data,
        ];
    }
}
