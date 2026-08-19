<?php

declare(strict_types=1);

use App\Enums\TenantPlan;

return [
    /*
    |--------------------------------------------------------------------------
    | Platform host
    |--------------------------------------------------------------------------
    |
    | Apex hostname (without scheme) that serves the marketing landing, the
    | agency onboarding and the super admin panel (`/super-admin`). Tenant
    | subdomains are built as `{slug}.{platform_host}`.
    |
    | Every other reserved host under this apex (`www.`, `admin.`, ...) is
    | 301'd here by App\Http\Middleware\RedirectToPlatformHost so the landing
    | lives at a single canonical URL.
    |
    | MONTREE_SUPER_ADMIN_HOST is the legacy name of this variable and is kept
    | as a fallback so existing deployments keep working.
    |
    */
    'platform_host' => env('MONTREE_PLATFORM_HOST', env('MONTREE_SUPER_ADMIN_HOST', 'montree.test')),

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

    /*
    |--------------------------------------------------------------------------
    | Idiomas soportados (multilanguage-es-en)
    |--------------------------------------------------------------------------
    |
    | Única fuente de verdad del catálogo de idiomas: la regla `in:` de
    | UpdateLocaleRequest, la cadena de resolución de App\Http\Middleware\SetLocale
    | y el selector del frontend leen de aca. `native` es el nombre del idioma en
    | sí mismo y NO se traduce: un hablante de inglés necesita reconocer
    | "English" aunque la pantalla esté en español.
    |
    */
    'locales' => [
        'es' => ['name' => 'Español', 'native' => 'Español'],
        'en' => ['name' => 'Inglés', 'native' => 'English'],
    ],

];
