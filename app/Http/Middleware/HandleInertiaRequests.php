<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Module;
use App\Http\Resources\AuthUserResource;
use App\Http\Resources\TenantConfigurationResource;
use App\Http\Resources\TenantResource;
use App\Models\CommissionSchedule;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Locale;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
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
            // WHY: el menú, la pantalla de roles y la home tienen que saber qué
            // módulos existen antes de pintar. No es diferida: llega en el primer render.
            'modules' => Module::flags(),
            'auth' => [
                'user' => $authUser,
                'permissions' => $authUser['permissions'] ?? [],
            ],
            'tenant' => $tenant !== null
                ? (new TenantResource($tenant))->resolve()
                : null,
            // WHY: `terms_body` admite 20.000 caracteres y esta prop viaja en TODA
            // respuesta Inertia, incluido el catálogo público. El único lugar que lo
            // edita es la configuración del panel, que lo recibe como prop propia.
            'tenantConfiguration' => $tenant?->configuration !== null
                ? Arr::except(
                    (new TenantConfigurationResource($tenant->configuration))->resolve(),
                    ['terms_body', 'terms_is_default'],
                )
                : null,
            // WHY: solo en el host de la plataforma. Los sitios de las agencias
            // muestran su propia identidad, no la de Montree.
            'platform' => $tenant === null
                ? [
                    'legal' => config('montree.legal'),
                    'commissionSchedule' => $this->globalCommissionSchedule(),
                ]
                : null,
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
            // WHY: la tabla de agencias entra al panel de un tenant con un `<form>`
            // nativo (POST a otro host, `target="_blank"`), y un formulario nativo
            // necesita el token en un input, no en la cabecera que arma Inertia.
            'csrfToken' => $request->session()->token(),
        ];
    }

    /**
     * @return array{currency: string, tiers: array<int, array{from: string, to: string|null, rate: string}>, max_charge: string|null}|null
     */
    private function globalCommissionSchedule(): ?array
    {
        $schedule = CommissionSchedule::query()->whereNull('tenant_id')->first();

        if ($schedule === null) {
            return null;
        }

        return [
            'currency' => $schedule->currency,
            'tiers' => $schedule->tiers,
            'max_charge' => $schedule->max_charge,
        ];
    }
}
