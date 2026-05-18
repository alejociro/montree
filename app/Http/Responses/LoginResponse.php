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

        if ($tenant === null) {
            return $this->buildRedirect($user, $request);
        }

        $pivot = $this->resolveMembership($user, $tenant);

        if ($pivot !== null && $pivot->status === TenantMembershipStatus::Suspended) {
            return $this->logoutSuspended($request);
        }

        if ($pivot === null && ! $user->hasRole(UserRole::SuperAdmin->value)) {
            $this->attachUserToTenant->handle($user, $tenant, UserRole::Customer, 'login');
        }

        return $this->buildRedirect($user, $request);
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

    private function buildRedirect(User $user, Request $request): Response
    {
        return $request->wantsJson()
            ? response()->json(['two_factor' => false])
            : redirect()->intended($this->home());
    }

    private function home(): string
    {
        return config('fortify.home', '/dashboard');
    }
}
