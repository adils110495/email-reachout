<?php

namespace App\Providers;

use Illuminate\Pagination\Paginator;
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
        // Use Bootstrap 5 pagination instead of the default Tailwind CSS style
        Paginator::useBootstrapFive();

        // Share the route-driven navigation with the layout and its partials
        View::composer(['layouts.app', 'layouts.partials.*'], function ($view) {
            $view->with('navSidebar', config('navigation.sidebar', []));
        });
    }
}
