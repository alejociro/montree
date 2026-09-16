<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\SuperAdmin\UpdateTenantCommissionAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantCommissionRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

final class UpdateTenantCommissionController extends Controller
{
    public function __invoke(
        UpdateTenantCommissionRequest $request,
        Tenant $tenant,
        UpdateTenantCommissionAction $updateCommission,
    ): RedirectResponse {
        $updateCommission->execute($tenant, $request->commissionType(), $request->commissionValue());

        return redirect()
            ->route('super-admin.tenants.show', $tenant)
            ->with('success', __('Cobro de plataforma actualizado.'));
    }
}
