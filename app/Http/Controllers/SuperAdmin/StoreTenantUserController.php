<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\SuperAdmin\CreateTenantUserAction;
use App\Exceptions\TeamException;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantUserRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

final class StoreTenantUserController extends Controller
{
    public function __invoke(
        StoreTenantUserRequest $request,
        Tenant $tenant,
        CreateTenantUserAction $createUser,
    ): RedirectResponse {
        try {
            $createUser->handle($tenant, $request->validated());
        } catch (TeamException $exception) {
            return back()->withErrors(['email' => $exception->getMessage()]);
        }

        return redirect()
            ->route('super-admin.tenants.show', $tenant)
            ->with('success', __('Usuario agregado. Se envió la invitación.'));
    }
}
