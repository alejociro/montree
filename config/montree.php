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
    | Default tour categories
    |--------------------------------------------------------------------------
    |
    | Catalogue seeded into every newly provisioned tenant so the tour form
    | never opens with an empty category select. Consumed by
    | App\Actions\Tenant\SeedDefaultCategoriesAction. Order in this array
    | becomes `display_order`.
    |
    */
    'default_categories' => [
        ['name' => 'Senderismo', 'icon' => 'mountain'],
        ['name' => 'Aventura', 'icon' => 'compass'],
        ['name' => 'Cultural', 'icon' => 'palette'],
        ['name' => 'Gastronomía', 'icon' => 'utensils'],
        ['name' => 'Avistamiento', 'icon' => 'binoculars'],
    ],

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
    | Pasajeros (tours-admin-passengers)
    |--------------------------------------------------------------------------
    |
    | Horas antes de `tour_dates.starts_at` en que se cierra la planilla para el
    | viajero (D10). Pasada esa hora, `PUT /api/v1/bookings/{bookingNumber}/travelers`
    | deja de aceptar cambios y la correccion de ultima hora se hace por la
    | agencia: el panel (`PUT /api/v1/admin/passengers/{traveler}`) NO mira esta
    | clave. La lee App\Models\Booking::travelerEditDeadline().
    |
    */
    'passengers' => [
        'traveler_edit_cutoff_hours' => (int) env('MONTREE_TRAVELER_EDIT_CUTOFF_HOURS', 24),
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
