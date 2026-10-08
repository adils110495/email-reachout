<?php

/*
|--------------------------------------------------------------------------
| Application Navigation
|--------------------------------------------------------------------------
|
| Single source of truth for the sidebar. Entries reference *route names* only
| - never hardcoded URLs - so links stay correct if a path in routes/web.php
| ever changes.
|
| Each entry supports:
|   label    - text shown in the menu
|   route    - route name resolved with route(); omit on a pure parent entry
|   active   - route-name pattern(s) passed to request()->routeIs(). On a
|              parent, list every pattern its descendants match so the module
|              stays open/highlighted while you are inside it.
|   icon     - Bootstrap Icons class (top-level sidebar items, dropdown)
|   children - optional sub-menu, same shape; nests to any depth
|
*/

return [

    'sidebar' => [

        [
            // Untitled group: the sidebar renders a .menu-title heading only
            // when 'title' is set, and one group needs no label above it.
            'items' => [
                [
                    'label'  => 'Dashboard',
                    'route'  => 'dashboard',
                    // "/" and "/dashboard" render the same page, so both names
                    // must light the item up.
                    'active' => ['dashboard', 'dashboard.index'],
                    'icon'   => 'bi-speedometer2',
                ],
                [
                    'label'  => 'Finder',
                    'route'  => 'finder.index',
                    'active' => ['finder.*'],
                    'icon'   => 'bi-search',
                ],
                [
                    'label'  => 'Verifier',
                    'route'  => 'verifier.index',
                    'active' => ['verifier.*'],
                    'icon'   => 'bi-patch-check',
                ],
                [
                    'label'  => 'Bulks',
                    'route'  => 'bulks.index',
                    'active' => ['bulks.*'],
                    'icon'   => 'bi-stack',
                ],
                [
                    'label'  => 'Leads',
                    'route'  => 'leads.index',
                    'active' => ['leads.index'],
                    'icon'   => 'bi-people',
                ],

                [
                    'label'  => 'Email Activity',
                    'route'  => 'email-activity.index',
                    'active' => ['email-activity.*'],
                    'icon'   => 'bi-activity',
                ],

                // Settings module - collapsible, holds every settings page.
                [
                    'label'    => 'Settings',
                    'active'   => ['templates.*', 'platforms.*', 'categories.*', 'addresses.*', 'mail-settings.*'],
                    'icon'     => 'bi-gear',
                    'children' => [
                        [
                            'label'    => 'Email Templates',
                            'route'    => 'templates.index',
                            'active'   => ['templates.*'],
                            'children' => [
                                [
                                    'label'  => 'All Templates',
                                    'route'  => 'templates.index',
                                    'active' => ['templates.index'],
                                ],
                                [
                                    'label'  => 'New Template',
                                    'route'  => 'templates.create',
                                    'active' => ['templates.create'],
                                ],
                            ],
                        ],
                        [
                            'label'  => 'Platforms',
                            'route'  => 'platforms.index',
                            'active' => ['platforms.*'],
                        ],
                        [
                            'label'  => 'Categories',
                            'route'  => 'categories.index',
                            'active' => ['categories.*'],
                        ],
                        [
                            'label'  => 'Addresses',
                            'route'  => 'addresses.index',
                            'active' => ['addresses.*'],
                        ],
                        [
                            'label'  => 'Mail Settings',
                            'route'  => 'mail-settings.index',
                            'active' => ['mail-settings.*'],
                        ],
                    ],
                ],
            ],
        ],

    ],
];
