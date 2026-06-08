<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    public function toResponse($request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = Tenant::current();

        if ($user === null) {
            return redirect()->intended($this->home());
        }

        if ($user->isSuperAdmin()) {
            return $this->redirectSuperAdmin($request);
        }

        if ($tenant === null) {
            return $this->buildRedirect($user, $request);
        }

        $pivot = $user->membershipFor($tenant);

        // WHY: membership is NOT created on login. A user who is not a member of
        // this agency must be told to register — silently enrolling them would let
        // one agency harvest another agency's customers via the shared login form.
        if ($pivot === null) {
            return $this->logoutWithError(
                $request,
                __('No tenés una cuenta en esta agencia. Registrate para continuar.'),
            );
        }

        if ($pivot->status !== TenantMembershipStatus::Active) {
            return $this->logoutWithError($request, $this->inactiveMessage($pivot));
        }

        return $this->buildRedirect($user, $request, $tenant);
    }

    private function tenantRole(User $user, Tenant $tenant): ?string
    {
        $user->loadRolesForTeam($tenant->id);

        return $user->getRoleNames()->first();
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

    private function inactiveMessage(TenantUser $pivot): string
    {
        return $pivot->status === TenantMembershipStatus::Suspended
            ? __('Tu cuenta está suspendida en esta agencia.')
            : __('Tu invitación a esta agencia está pendiente de aceptación.');
    }

    private function logoutWithError(Request $request, string $message): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->withErrors([
            'email' => $message,
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
