<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Lets the school's public website read /api/v1/website/* with the shared key (WEBSITE_API_KEY in this portal's .env). */
class VerifyWebsiteKey
{
    public function handle(Request $request, Closure $next)
    {
        $key = (string) config('services.website.key');
        if ($key === '') {
            return response()->json(['success' => false, 'message' => 'WEBSITE_API_KEY is not set on the portal.'], 503);
        }
        if (!hash_equals($key, (string) $request->header('X-Website-Key'))) {
            return response()->json(['success' => false, 'message' => 'Invalid website key.'], 403);
        }
        return $next($request);
    }
}
