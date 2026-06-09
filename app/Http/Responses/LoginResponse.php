<?php

declare(strict_types=1);

namespace App\Http\Responses;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\Auth\CrossHostLoginHandoff;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;
use Symfony\Component\HttpFoundation\Response;

final class LoginResponse implements LoginResponseContract
{
    public function __construct(private CrossHostLoginHandoff $handoff) {}

    public function toResponse($request): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = Tenant::current();

        if ($user === null) {
            return redirect()->intended($this->home());
        }

        if ($user->isSuperAdmin()) {
            return $this->redirectSuperAdmin($user, $request);
        }

        if ($tenant === null) {
            return $this->routeFromPlatform($user, $request);
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

    private function redirectSuperAdmin(User $user, Request $request): Response
    {
        $host = (string) config('montree.super_admin_host', 'montree.test');
        $dashboard = '/super-admin/dashboard';

        // WHY: when the login already happens on the admin host, the session cookie
        // lives here — no cross-host handoff needed.
        if (strtolower($request->getHost()) === strtolower($host)) {
            return $request->wantsJson()
                ? response()->json(['two_factor' => false, 'redirect' => $dashboard])
                : redirect()->intended($dashboard);
        }

        // Cross-host: isolated per-subdomain sessions mean the cookie set here will
        // not reach the admin host. Hand off via a single-use token.
        return $this->crossHostHandoff($user, $request, $host, $dashboard);
    }

    private function hostUrl(Request $request, string $host): string
    {
        $scheme = $request->getScheme();
        $port = $request->getPort();
        $portSuffix = in_array($port, [80, 443], true) ? '' : ':'.$port;

        return "{$scheme}://{$host}{$portSuffix}";
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

    private function buildRedirect(User $user, Request $request, Tenant $tenant): Response
    {
        $target = $this->roleHome($user, $tenant);

        return $request->wantsJson()
            ? response()->json(['two_factor' => false, 'redirect' => $target])
            : redirect()->intended($target);
    }

    /**
     * Login from the platform host (no tenant): route the user to the agency they
     * belong to and log them in there via a cross-host handoff.
     */
    private function routeFromPlatform(User $user, Request $request): Response
    {
        /** @var TenantUser|null $membership */
        $membership = TenantUser::query()
            ->where('user_id', $user->getKey())
            ->where('status', TenantMembershipStatus::Active->value)
            ->orderByDesc('joined_at')
            ->first();

        $tenant = $membership !== null ? Tenant::find($membership->tenant_id) : null;

        if ($tenant === null) {
            return $this->logoutWithError(
                $request,
                __('Tu cuenta no está asociada a ninguna agencia.'),
            );
        }

        return $this->crossHostHandoff(
            $user,
            $request,
            $this->tenantHost($request, $tenant),
            $this->roleHome($user, $tenant),
        );
    }

    private function roleHome(User $user, Tenant $tenant): string
    {
        return match ($this->tenantRole($user, $tenant)) {
            UserRole::Admin->value, UserRole::Operator->value => '/admin/dashboard',
            UserRole::Guide->value => '/guide/schedule',
            default => $this->home(),
        };
    }

    private function tenantHost(Request $request, Tenant $tenant): string
    {
        $base = preg_replace('/^www\./', '', strtolower($request->getHost()));

        return $tenant->slug.'.'.$base;
    }

    /**
     * Issue a single-use token to log the user in on a different host, then destroy
     * the origin-host session so nothing lingers where it shouldn't.
     */
    private function crossHostHandoff(User $user, Request $request, string $host, string $path): Response
    {
        $token = $this->handoff->issue($user, $path);
        $url = $this->hostUrl($request, $host)."/auth/handoff/{$token}";

        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['two_factor' => false, 'redirect' => $url]);
        }

        // WHY: the login form posts via Inertia (XHR). A redirect to a different
        // ORIGIN can't be followed as an Inertia response, so the visit fails
        // silently. Inertia::location returns a 409 with X-Inertia-Location so the
        // client does a full-page navigation to the destination host. For
        // non-Inertia requests it falls back to a normal 302 redirect.
        return Inertia::location($url);
    }

    private function home(): string
    {
        return '/';
    }
}
