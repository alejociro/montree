<?php

declare(strict_types=1);

use App\Enums\TenantPlan;

return [
    /*
    |--------------------------------------------------------------------------
    | Super admin host
    |--------------------------------------------------------------------------
    |
    | Hostname (without scheme) the super admin panel responds to. All
    | super_admin routes are bound to this host via Route::domain().
    |
    */
    'super_admin_host' => env('MONTREE_SUPER_ADMIN_HOST', 'montree.test'),

    /*
    |--------------------------------------------------------------------------
    | Reserved hosts
    |--------------------------------------------------------------------------
    |
    | Comma separated hostnames that NEVER resolve to a tenant (platform
    | landing, super admin, admin and api hosts). Consumed by
    | App\Multitenancy\SubdomainTenantFinder.
    |
    */
    'reserved_hosts' => env(
        'MONTREE_RESERVED_HOSTS',
        'montree.app,www.montree.app,montree.test,www.montree.test,admin.montree.app,admin.montree.test,api.montree.app,api.montree.test,localhost,127.0.0.1',
    ),

    /*
    |--------------------------------------------------------------------------
    | Self-serve onboarding (F016)
    |--------------------------------------------------------------------------
    |
    | Trial length and default plan a newly registered agency enters once its
    | founder verifies their email. Read by App\Actions\Onboarding.
    |
    */
    'onboarding' => [
        'trial_days' => (int) env('MONTREE_ONBOARDING_TRIAL_DAYS', 14),
        'default_plan' => TenantPlan::Professional,
    ],
];
