<?php

namespace App\Http\Middleware;

use App\Models\FeatureFlag;
use Closure;
use Illuminate\Http\Request;

/**
 * Same module switches as the web portal (FeatureRouteGuard), for the API:
 * `api.feature:results` returns 403 when the "results" module is OFF.
 * Fail-open like the web guard: a flag that was never set never blocks.
 */
class ApiFeatureEnabled
{
    public function handle(Request $request, Closure $next, string ...$keys)
    {
        try {
            $on = !$keys || FeatureFlag::anyEnabled($keys);
        } catch (\Throwable $e) {
            $on = true;
        }

        if (!$on) {
            return response()->json([
                'success' => false,
                'code'    => 'module_disabled',
                'message' => 'This feature is turned off by the school.',
            ], 403);
        }

        return $next($request);
    }
}
