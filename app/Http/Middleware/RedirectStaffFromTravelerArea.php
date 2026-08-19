<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * WHY: F018 B4 — el staff de la agencia no tiene zona de viajero. `EnsureTenantMember`
 * solo exige membresía activa, así que un admin podía abrir `/account/*` y quedarse ahí
 * sin salida visible (apunte #2 del equipo). El corte es por permiso de panel, no por
 * rol: quien puede entrar a `admin/*` o a `guide/*` se devuelve a su home de rol.
 *
 * Va DESPUÉS de `tenant_member.only`, que ya resolvió membresía, contexto de equipo de
 * spatie y el caso `super_admin` (lo manda a `/` antes de llegar acá).
 */
final class RedirectStaffFromTravelerArea
{
    public function __construct(private RoleHomeResolver $roleHome) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = Tenant::current();

        if ($user === null || $tenant === null) {
            return $next($request);
        }

        $home = $this->roleHome->homeFor($user, $tenant);

        if ($home === RoleHomeResolver::TRAVELER_HOME) {
            return $next($request);
        }

        return redirect()->to($home);
    }
}
