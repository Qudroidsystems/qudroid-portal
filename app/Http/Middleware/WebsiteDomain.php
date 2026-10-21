<?php

namespace App\Http\Middleware;

use App\Services\Website\WebsiteContent as W;
use Closure;
use Illuminate\Http\Request;

/**
 * Keeps the school website and the portal on their own domains once the
 * admin has set the website domain (Admin → Website → Settings → Domains):
 *
 *   www.school.ng/news         → website
 *   www.school.ng/login        → redirected to the portal (portal.school.ng/login)
 *   portal.school.ng/          → portal login (as before the website existed)
 *   portal.school.ng/news      → redirected to www.school.ng/news
 *
 * Nothing changes while no website domain is set: "/" opens the portal
 * login and the website can be previewed at /website-preview.
 */
class WebsiteDomain
{
    /** Website routes that may be served from either domain (files). */
    protected const SHARED = ['site.media', 'site.app.apk'];

    public function handle(Request $request, Closure $next)
    {
        try {
            if (W::websiteDomain() === '') return $next($request);
        } catch (\Throwable $e) {
            return $next($request); // tables not migrated yet
        }

        $name = (string) optional($request->route())->getName();
        $isSite = str_starts_with($name, 'site.');
        $onWebsite = W::isWebsiteHost($request->getHost());
        $path = '/' . ltrim($request->path(), '/');
        $query = $request->getQueryString();
        $tail = ($path === '/' ? '' : $path) . ($query ? '?' . $query : '');

        if ($onWebsite && !$isSite) {
            // A portal page asked for on the website's domain → send it to the portal.
            $target = W::portalUrl($tail);
            if ($this->differentHost($target, $request)) {
                return redirect()->away($target, $request->isMethod('GET') ? 302 : 307);
            }
            return $next($request);
        }

        if (!$onWebsite && $isSite && !in_array($name, self::SHARED, true)) {
            // "/" on the portal's domain is the portal login, as it always was.
            if ($name === 'site.home') return redirect('/login');
            if ($request->isMethod('GET')) {
                $target = W::websiteUrl($name === 'site.preview' ? '' : $tail);
                if ($this->differentHost($target, $request)) return redirect()->away($target, 302);
            }
        }

        return $next($request);
    }

    protected function differentHost(string $url, Request $request): bool
    {
        $host = strtolower((string) parse_url($url, PHP_URL_HOST));
        return $host !== '' && $host !== strtolower($request->getHost());
    }
}
