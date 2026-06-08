<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Enforce that the authenticated user is an ACTIVE member of the current tenant
 * on every request — not only at login. Without this, a globally-authenticated
 * user (sessions, shared cookies, or a membership suspended mid-session) could
 * reach tenant-scoped pages they no longer belong to.
 */
final class EnsureTenantMember
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            abort(401);
        }

        // WHY: super_admin is global (no tenant membership) and operates from the
        // reserved admin host; let it pass without a pivot lookup.
        if ($user->isSuperAdmin()) {
            return $next($request);
        }

        $tenant = Tenant::current();

        if ($tenant === null) {
            abort(403);
        }

        if (! $user->isActiveMemberOf($tenant)) {
            return $this->denyMembership($request);
        }

        $user->loadRolesForTeam($tenant->id);

        return $next($request);
    }

    private function denyMembership(Request $request): Response
    {
        if ($request->expectsJson()) {
            abort(403, __('No tenés acceso a esta agencia.'));
        }

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('No tenés una cuenta activa en esta agencia.'),
        ]);
    }
}
