<?php

namespace App\Providers;

use App\Database\MySqlConnection;
use Carbon\CarbonInterface;
use Illuminate\Database\Connection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Dates bound into SQL are converted to the app timezone first.
        Connection::resolverFor('mysql', fn ($pdo, $database, $prefix, $config) => new MySqlConnection($pdo, $database, $prefix, $config));
    }

    public function boot(): void
    {
        // Every date Laravel creates - now(), today(), Eloquent date casts and the values it
        // saves - is in the app timezone (IST). Eloquent writes a Carbon's wall-clock time as it
        // is, so a time carrying another zone (UTC from a mail header, a sequence's local time,
        // a test clock) would otherwise be stored shifted by the difference.
        Date::useCallable(fn ($date) => $date instanceof CarbonInterface ? $date->setTimezone(config('app.timezone')) : $date);

        // Use Bootstrap 5 pagination instead of the default Tailwind CSS style
        Paginator::useBootstrapFive();

        // Share the route-driven navigation with the layout and its partials
        View::composer(['layouts.app', 'layouts.partials.*'], function ($view) {
            $view->with('navSidebar', config('navigation.sidebar', []));
        });
    }
}
