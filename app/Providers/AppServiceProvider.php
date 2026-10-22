<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Pagination in the Bootstrap 5 theme (matches the Velzon UI).
        Paginator::useBootstrapFive();

        // The Super Administrator passes every permission check, including
        // permissions added by modules that are built later.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'isSuperAdmin') && $user->isSuperAdmin() ? true : null;
        });

        // Branding for every view (layout, login, e-mails).
        View::share('brand', config('qudroid'));
    }
}
