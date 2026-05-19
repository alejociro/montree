<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureSuperAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        // WHY: super_admin role lives on sentinel team_id=0 (see RolesAndPermissionsSeeder + multi-tenancy.md §9.3).
        setPermissionsTeamId(0);
        $user->unsetRelation('roles');

        if (! $user->hasRole(UserRole::SuperAdmin->value)) {
            abort(403);
        }

        return $next($request);
    }
}
