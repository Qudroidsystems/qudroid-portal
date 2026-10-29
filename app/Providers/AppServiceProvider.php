<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // One per request, so meeting and call lookups are shared across a page.
        $this->app->scoped(\App\Services\Presence\Presence::class);
    }

    public function boot(): void
    {
        // Website pages all see the website's settings as $cfg.
        \Illuminate\Support\Facades\View::composer('site.*', function ($view) {
            if (!array_key_exists('cfg', $view->getData())) $view->with('cfg', app(\App\Services\Website\Site::class)->config());
        });
        // Pagination in the Bootstrap 5 theme (matches the Velzon UI).
        Paginator::useBootstrapFive();

        // Qmail limits, counted per person and per action (plain throttle:N,1 shares one count across routes).
        // Public check-in pages: counted per visitor and per page, so one route can't use up another's allowance.
        RateLimiter::for('checkin-public', function ($request) {
            $name = (string) $request->route()?->getName();
            $per = $name === 'checkin.public.store' ? Limit::perMinutes(10, 10) : Limit::perMinute(60);

            return $per->by(($request->user()?->id ?: $request->ip()) . '|' . $name);
        });
        RateLimiter::for('mail', function ($request) {
            $name = (string) preg_replace('/^api\.v1\./', '', (string) $request->route()?->getName());
            $per = ['mail.send' => 30, 'mail.connect' => 10, 'mail.sync' => 10, 'mail.drafts.save' => 120, 'mail.poll' => 120,
                'mail.admin.store' => 20, 'mail.admin.password' => 10, 'mail.admin.check' => 20, 'mail.settings.update' => 20][$name] ?? 240;

            return Limit::perMinute($per)->by(($request->user()?->id ?: $request->ip()) . '|' . $name);
        });

        // The Super Administrator passes every permission check, including
        // permissions added by modules that are built later.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null;
        });

        // Workflow automations listen for record changes (tasks, deals, invoices...).
        \App\Services\Workflows\Engine::listen();

        // Branding for every view (layout, login, e-mails).
        View::share('brand', config('qudroid'));
    }
}
