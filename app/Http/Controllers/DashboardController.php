<?php

namespace App\Http\Controllers;

use App\Http\Controllers\ActivityLogController;
use App\Models\User;
use App\Support\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

/**
 * Qlouds Workspace home: the app launcher every user sees, plus the
 * organisation overview for people allowed to see it.
 */
class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();
        $modules = Modules::all($user);

        $overview = null;
        if ($user->can('View dashboard')) {
            $since = now()->subMinutes(ActivityLogController::ONLINE_MINUTES);
            $types = User::query()->selectRaw('user_type, COUNT(*) as total')->groupBy('user_type')->pluck('total', 'user_type');

            $overview = [
                'users'       => (int) $types->sum(),
                'types'       => $types,
                'disabled'    => User::where('is_disabled', true)->count(),
                'online'      => User::where('last_seen_at', '>=', $since)->count(),
                'roles'       => Role::count(),
                'signins'     => Schema::hasTable('activity_logs')
                    ? DB::table('activity_logs')->where('event', 'login')->where('created_at', '>=', now()->startOfDay())->distinct()->count('user_id')
                    : 0,
                'newThisMonth'=> User::where('created_at', '>=', now()->startOfMonth())->count(),
                'recent'      => Schema::hasTable('activity_logs') && $user->can('View activity log')
                    ? DB::table('activity_logs as a')->leftJoin('users as u', 'u.id', '=', 'a.user_id')
                        ->orderByDesc('a.id')->limit(8)->get(['a.event', 'a.description', 'a.created_at', 'u.name'])
                    : collect(),
            ];
        }

        $notifications = Schema::hasTable('notifications')
            ? $user->notifications()->latest()->limit(6)->get()
            : collect();

        return view('dashboard.index', [
            'pagetitle'     => 'Dashboard',
            'modules'       => $modules,
            'overview'      => $overview,
            'notifications' => $notifications,
            'unread'        => Schema::hasTable('notifications') ? $user->unreadNotifications()->count() : 0,
        ]);
    }
}
