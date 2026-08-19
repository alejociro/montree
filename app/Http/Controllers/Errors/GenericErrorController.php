<?php

declare(strict_types=1);

namespace App\Http\Controllers\Errors;

use App\Http\Controllers\Controller;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

/**
 * WHY: sin esto, cualquier error HTTP de una página (403 al abrir un módulo que
 * el rol no tiene, 404 de un enlace viejo) salía como la página cruda de Symfony
 * — "403 Forbidden", en inglés, sin marca y sin ningún enlace de vuelta. Era el
 * callejón sin salida que reportaron las pruebas con usuarios reales.
 *
 * Solo aplica a peticiones de navegación: las respuestas JSON conservan su shape
 * (`contracts.md` §4), y las páginas de estado del tenant las sigue resolviendo
 * `ResolveTenant` con su propio copy.
 */
final class GenericErrorController extends Controller
{
    /** Códigos que tienen copy propio; el resto cae en el mensaje genérico. */
    private const RENDERABLE = [403, 404, 419, 429, 500, 503];

    public function __construct(private readonly RoleHomeResolver $roleHome) {}

    public function __invoke(Request $request, int $status): ?Response
    {
        if (! in_array($status, self::RENDERABLE, true)) {
            return null;
        }

        return Inertia::render('Errors/Generic', [
            'status' => $status,
            'homeUrl' => $this->homeUrl($request),
        ])
            ->toResponse($request)
            ->setStatusCode($status);
    }

    /**
     * A dónde manda el botón "Volver al inicio": el mismo destino que el login,
     * para no ofrecerle al usuario otra puerta que también responda 403.
     */
    private function homeUrl(Request $request): string
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return '/';
        }

        if ($user->isSuperAdmin()) {
            return '/super-admin/dashboard';
        }

        $tenant = Tenant::current();

        if ($tenant === null || ! $user->isActiveMemberOf($tenant)) {
            return '/';
        }

        return $this->roleHome->homeFor($user, $tenant);
    }
}
