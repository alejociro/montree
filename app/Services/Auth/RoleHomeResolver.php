<?php

declare(strict_types=1);

namespace App\Services\Auth;

use App\Models\Tenant;
use App\Models\User;

/**
 * WHY: "¿dónde vive este usuario dentro de la agencia?" es una sola respuesta con tres
 * llamadores — el redirect de login, `GET /dashboard` (bug A3) y la barrera de la zona
 * de viajero (bug B4). Vivía privada dentro de `LoginResponse::roleHome()`.
 *
 * Se resuelve por permiso y no por rol por dos razones: F018 sacó el rol del backend
 * (nadie pregunta `hasRole()`), y el permiso garantiza que el destino sea una ruta que
 * el usuario puede abrir — `dashboard.view` es el gate de todo `admin/*` y
 * `guide.schedule.view` el de `guide/*`. Con la matriz de roles por rol, un `sales`
 * (que tiene `dashboard.view` pero no aparecía en el match por rol) terminaba en la
 * landing pública en vez del panel.
 */
final class RoleHomeResolver
{
    public const ADMIN_HOME = '/admin/dashboard';

    public const GUIDE_HOME = '/guide/schedule';

    public const TRAVELER_HOME = '/';

    public function homeFor(User $user, Tenant $tenant): string
    {
        $user->loadRolesForTeam($tenant->id);

        if ($user->can('dashboard.view')) {
            return self::ADMIN_HOME;
        }

        if ($user->can('guide.schedule.view')) {
            return self::GUIDE_HOME;
        }

        return self::TRAVELER_HOME;
    }
}
