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
|   module   - hide unless the user has this module (User::MODULES)
|   can      - hide unless the user passes this Gate ability
|   children - optional sub-menu, same shape; nests to any depth. A parent
|              whose children are all hidden is hidden too.
|
| module / can only decide what is SHOWN. The same names guard the routes
| (module: / can: middleware), which is what actually enforces access.
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
                    'module' => 'finder',
                ],
                [
                    'label'  => 'Verifier',
                    'route'  => 'verifier.index',
                    'active' => ['verifier.*'],
                    'icon'   => 'bi-patch-check',
                    'module' => 'verifier',
                ],
                [
                    'label'  => 'Bulks',
                    'route'  => 'bulks.index',
                    'active' => ['bulks.*'],
                    'icon'   => 'bi-stack',
                    'module' => 'bulks',
                ],
                [
                    'label'  => 'Leads',
                    'route'  => 'leads.index',
                    'active' => ['leads.index'],
                    'icon'   => 'bi-people',
                    'module' => 'leads',
                ],

                [
                    'label'  => 'GMB Leads',
                    'route'  => 'gmb-leads.index',
                    'active' => ['gmb-leads.*'],
                    'icon'   => 'bi-geo-alt',
                    'module' => 'gmb_leads',
                ],
                [
                    'label'  => 'Deals',
                    'route'  => 'cash-leads.index',
                    'active' => ['cash-leads.*'],
                    'icon'   => 'bi-cash-coin',
                    'module' => 'deals',
                ],
                [
                    'label'  => 'Email Activity',
                    'route'  => 'email-activity.index',
                    'active' => ['email-activity.*'],
                    'icon'   => 'bi-activity',
                    'module' => 'email_activity',
                ],
                [
                    'label'  => 'WhatsApp',
                    'route'  => 'whatsapp.index',
                    'active' => ['whatsapp.*'],
                    'icon'   => 'bi-whatsapp',
                    'module' => 'whatsapp',
                ],

                // Settings module - collapsible, holds every settings page.
                [
                    'label'    => 'Settings',
                    'active'   => ['templates.*', 'platforms.*', 'categories.*', 'addresses.*', 'mail-settings.*', 'whatsapp-settings.*', 'branding.*', 'users.*'],
                    'icon'     => 'bi-gear',
                    'children' => [
                        [
                            'label'    => 'Email Templates',
                            'route'    => 'templates.index',
                            'active'   => ['templates.*'],
                            'module'   => 'templates',
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
                            'module' => 'platforms',
                        ],
                        [
                            'label'  => 'Categories',
                            'route'  => 'categories.index',
                            'active' => ['categories.*'],
                            'module' => 'categories',
                        ],
                        [
                            'label'  => 'Addresses',
                            'route'  => 'addresses.index',
                            'active' => ['addresses.*'],
                            'module' => 'addresses',
                        ],
                        [
                            'label'  => 'Mail Settings',
                            'route'  => 'mail-settings.index',
                            'active' => ['mail-settings.*'],
                            'module' => 'mail_settings',
                        ],
                        [
                            'label'  => 'WhatsApp Settings',
                            'route'  => 'whatsapp-settings.index',
                            'active' => ['whatsapp-settings.*'],
                            'module' => 'whatsapp',
                        ],
                        [
                            'label'  => 'Branding',
                            'route'  => 'branding.index',
                            'active' => ['branding.*'],
                            'can'    => 'manage-branding',
                        ],
                        [
                            'label'  => 'Users',
                            'route'  => 'users.index',
                            'active' => ['users.*'],
                            'can'    => 'manage-users',
                        ],
                    ],
                ],
            ],
        ],

    ],
];
