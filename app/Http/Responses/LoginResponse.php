<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Tenant\AttachUserToTenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    public function __construct(private AttachUserToTenant $attachUserToTenant) {}

    public function toResponse($request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = Tenant::current();

        if ($user === null) {
            return redirect()->intended($this->home());
        }

        if ($this->isSuperAdmin($user)) {
            return $this->redirectSuperAdmin($request);
        }

        if ($tenant === null) {
            return $this->buildRedirect($user, $request);
        }

        $pivot = $this->resolveMembership($user, $tenant);

        if ($pivot !== null && $pivot->status === TenantMembershipStatus::Suspended) {
            return $this->logoutSuspended($request);
        }

        if ($pivot === null) {
            $this->attachUserToTenant->handle($user, $tenant, UserRole::Customer, 'login');
        }

        return $this->buildRedirect($user, $request, $tenant);
    }

    private function tenantRole(User $user, Tenant $tenant): ?string
    {
        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');

        return $user->getRoleNames()->first();
    }

    private function isSuperAdmin(User $user): bool
    {
        setPermissionsTeamId(0);
        $user->unsetRelation('roles');

        return $user->hasRole(UserRole::SuperAdmin->value);
    }

    private function redirectSuperAdmin(Request $request): Response
    {
        $host = (string) config('montree.super_admin_host', 'admin.montree.test');
        $scheme = $request->getScheme();
        $port = $request->getPort();
        $portSuffix = in_array($port, [80, 443], true) ? '' : ':'.$port;
        $url = "{$scheme}://{$host}{$portSuffix}/super-admin/dashboard";

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $url])
            : redirect()->away($url);
    }

    private function resolveMembership(User $user, Tenant $tenant): ?TenantUser
    {
        /** @var TenantUser|null $pivot */
        $pivot = TenantUser::query()
            ->where('tenant_id', $tenant->id)
            ->where('user_id', $user->id)
            ->first();

        return $pivot;
    }

    private function logoutSuspended(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => __('Tu cuenta está suspendida en esta agencia.'),
        ]);
    }

    private function buildRedirect(User $user, Request $request, ?Tenant $tenant = null): Response
    {
        $target = $this->home();

        if ($tenant !== null) {
            $role = $this->tenantRole($user, $tenant);
            $target = match ($role) {
                UserRole::Admin->value, UserRole::Operator->value => '/admin/dashboard',
                UserRole::Guide->value => '/guide/schedule',
                default => $this->home(),
            };
        }

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $target])
            : redirect()->intended($target);
    }

    private function home(): string
    {
        return '/';
    }
}
