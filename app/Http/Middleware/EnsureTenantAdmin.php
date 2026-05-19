<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');

        if (! $user->hasAnyRole([UserRole::Admin->value, UserRole::Operator->value])) {
            abort(403);
        }

        return $next($request);
    }
}
