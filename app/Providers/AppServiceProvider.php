<?php

namespace App\Providers;

use App\Models\User;
use App\Services\UserAccessService;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Scoped: one instance per request / per queued job, so an acting user
        // set by one job can never carry over into the next.
        $this->app->scoped(UserAccessService::class);
    }

    public function boot(): void
    {
        // Use Bootstrap 5 pagination instead of the default Tailwind CSS style
        Paginator::useBootstrapFive();

        $this->defineGates();

        // Share the route-driven navigation with the layout and its partials,
        // minus anything the signed-in user may not open.
        View::composer(['layouts.app', 'layouts.partials.*'], function ($view) {
            $user = auth()->user();

            $view->with('navSidebar', array_map(
                fn (array $group) => ['items' => $this->visibleNav($group['items'] ?? [], $user)] + $group,
                config('navigation.sidebar', []),
            ));
        });
    }

    /**
     * The one place authorization rules are named. Super admin passes every
     * check through Gate::before, so no ability below has to mention it.
     */
    private function defineGates(): void
    {
        Gate::before(fn (User $user) => $user->isSuperAdmin() ? true : null);

        Gate::define('module', fn (User $user, string $module) => $user->canUseModule($module));

        // Super admin only (Gate::before) - nobody else, whatever their modules.
        Gate::define('manage-users', fn (User $user) => false);
        Gate::define('manage-branding', fn (User $user) => false);
    }

    /**
     * Drop sidebar items the user cannot open ('module' / 'can' keys), and any
     * parent left with no children.
     *
     * @param  array<int, array<string, mixed>>  $items
     * @return array<int, array<string, mixed>>
     */
    private function visibleNav(array $items, ?User $user): array
    {
        $visible = [];

        foreach ($items as $item) {
            if (isset($item['module']) && ! $user?->can('module', $item['module'])) {
                continue;
            }

            if (isset($item['can']) && ! $user?->can($item['can'])) {
                continue;
            }

            if (isset($item['children'])) {
                $item['children'] = $this->visibleNav($item['children'], $user);

                if ($item['children'] === []) {
                    continue;
                }
            }

            $visible[] = $item;
        }

        return $visible;
    }
}
