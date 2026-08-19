<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Http\Resources\AuthUserResource;
use App\Http\Resources\TenantConfigurationResource;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $tenant = Tenant::current();
        $tenant?->loadMissing('configuration');

        /** @var User|null $user */
        $user = $request->user();

        $authUser = $user !== null
            ? (new AuthUserResource($user, $tenant))->resolve()
            : null;

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'locale' => app()->getLocale(),
            'locales' => Locale::options(),
            // WHY: no es prop diferida. El primer render tiene que salir ya traducido
            // (si no, la pantalla parpadea de idioma) y el catalogo pesa pocos KB.
            'translations' => Locale::translations(app()->getLocale()),
            'auth' => [
                'user' => $authUser,
                'permissions' => $authUser['permissions'] ?? [],
            ],
            'tenant' => $tenant !== null
                ? (new TenantResource($tenant))->resolve()
                : null,
            'tenantConfiguration' => $tenant?->configuration !== null
                ? (new TenantConfigurationResource($tenant->configuration))->resolve()
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
