<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WHY: desde F018 este middleware ya NO pregunta por rol — quien entra a `guide/*` lo
 * decide `can:guide.*` por ruta (bug B2: antes dejaba pasar a admin y operator sin que
 * fueran guías). Queda a cargo de la membresía activa, que el permiso no valida.
 */
final class EnsureTenantGuide
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

        if (! $user->isActiveMemberOf($tenant)) {
            abort(403);
        }

        $user->loadRolesForTeam($tenant->id);

        return $next($request);
    }
}
