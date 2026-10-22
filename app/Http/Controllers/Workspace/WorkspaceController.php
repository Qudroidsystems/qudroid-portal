<?php

namespace App\Http\Controllers\Workspace;

use App\Http\Controllers\Controller;
use App\Models\Connect\Call;
use App\Models\Connect\Participant;
use App\Models\Meetings\Meeting;
use App\Models\User;
use App\Services\Connect\ConnectService;
use App\Services\Drive\DriveService;
use App\Services\Messaging\PortalNotifier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/**
 * The workspace home: what needs you in chat, calls going on now, your
 * next meetings, files you worked on lately, and who is online.
 */
class WorkspaceController extends Controller
{
    public function index(Request $request, ConnectService $connect)
    {
        $me = $request->user();
        $inbox = $connect->inbox($me);
        $conversationIds = Participant::where('user_id', $me->id)->whereNull('left_at')->pluck('conversation_id');

        return view('modules.workspace.index', [
            'me' => $me,
            'unread' => $inbox->filter(fn ($c) => $c->unread > 0)->take(8)->values(),
            'recentChats' => $inbox->take(6),
            'calls' => Call::with('conversation', 'starter')->whereNull('ended_at')->whereIn('conversation_id', $conversationIds)->latest('id')->limit(5)->get(),
            'meetings' => ($me->can('View meetings') || $me->can('Manage meetings')) && Route::has('meetings.index')
                ? Meeting::with('room')->for($me)->active()->where('ends_at', '>=', now())->orderBy('starts_at')->limit(6)->get() : collect(),
            'files' => app(DriveService::class)->recent($me, 8),
            'drive' => app(DriveService::class),
            'online' => User::whereIn('id', PortalNotifier::userIdsWithPermission('Use connect'))->where('is_disabled', false)
                ->whereKeyNot($me->id)->where('last_seen_at', '>=', now()->subMinutes(2))->orderBy('name')->limit(24)->get(),
        ]);
    }
}
