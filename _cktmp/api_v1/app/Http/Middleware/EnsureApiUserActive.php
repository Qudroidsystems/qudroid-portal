<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Mobile API guard rails, run after auth:sanctum:
 *  - a disabled account loses access at once (its token is revoked);
 *  - a user still on a temporary password may only reach the auth endpoints
 *    (me, change password, logout) until they set their own.
 */
class EnsureApiUserActive
{
    protected array $allowedWhileTemporary = [
        'api.v1.auth.me', 'api.v1.auth.password', 'api.v1.auth.logout', 'api.v1.devices.store',
    ];

    public function handle(Request $request, Closure $next)
    {
        $user = $request->user();
        if (!$user) return $next($request);

        if (!empty($user->is_disabled)) {
            $user->currentAccessToken()?->delete();
            return response()->json([
                'success' => false,
                'code'    => 'account_disabled',
                'message' => 'This account has been disabled. Please contact the school.',
            ], 403);
        }

        if (!empty($user->must_change_password) && !$request->routeIs(...$this->allowedWhileTemporary)) {
            return response()->json([
                'success' => false,
                'code'    => 'password_change_required',
                'message' => 'Please choose your own password to continue.',
            ], 403);
        }

        return $next($request);
    }
}
