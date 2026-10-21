<?php

namespace App\Providers;

use App\Services\Website\WebsiteContent;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Shares the website settings, school details and menu with every public
 * website view (resources/views/website/*, except the admin screens).
 */
class WebsiteServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        View::composer(['website.*', 'website.partials.*'], function ($view) {
            if (str_starts_with($view->getName(), 'website.admin')) return;
            $view->with([
                'site'   => WebsiteContent::settings(),
                'school' => WebsiteContent::school(),
                'menu'   => WebsiteContent::menu(),
            ]);
        });
    }
}
