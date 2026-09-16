<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\SuperAdmin\CreateTenantAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\StoreTenantRequest;
use Illuminate\Http\RedirectResponse;

final class StoreTenantController extends Controller
{
    public function __invoke(StoreTenantRequest $request, CreateTenantAction $createTenant): RedirectResponse
    {
        $tenant = $createTenant->handle($request->validated());

        return redirect()
            ->route('super-admin.tenants.show', $tenant)
            ->with('success', __('Agencia creada. Se envió la invitación al admin.'));
    }
}
