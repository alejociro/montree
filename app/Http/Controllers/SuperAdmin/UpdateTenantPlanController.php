<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\SuperAdmin\UpdateTenantPlanAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantPlanRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;

final class UpdateTenantPlanController extends Controller
{
    public function __invoke(
        UpdateTenantPlanRequest $request,
        Tenant $tenant,
        UpdateTenantPlanAction $updatePlan,
    ): RedirectResponse {
        $updatePlan->handle($tenant, $request->newPlan());

        return redirect()
            ->route('super-admin.tenants.show', $tenant)
            ->with('success', __('Plan actualizado correctamente.'));
    }
}
