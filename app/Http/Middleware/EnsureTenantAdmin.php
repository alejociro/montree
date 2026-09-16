<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WHY: desde F018 este middleware ya NO pregunta por rol — la autorización de cada ruta
 * de `admin/*` la resuelve `can:<permiso>`. Lo que sigue siendo suyo es la membresía:
 * un miembro suspendido conserva sus filas en `model_has_roles`, así que sin este
 * chequeo seguiría pasando el `can:` después de que el equipo lo suspendiera.
 *
 * La excepción es el super admin: entra al panel de cualquier agencia desde la
 * tabla de la plataforma sin estar en `tenant_user`, y su autorización la
 * resuelve el `Gate::before` de `AppServiceProvider`.
 */
final class EnsureTenantAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        $tenant = Tenant::current();

        if ($tenant === null) {
            abort(403);
        }

        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        if (! $user->isActiveMemberOf($tenant)) {
            abort(403);
        }

        $user->loadRolesForTeam($tenant->id);

        return $next($request);
    }
}
