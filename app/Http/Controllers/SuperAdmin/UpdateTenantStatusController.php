<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\SuperAdmin\UpdateTenantStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantStatusRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use RuntimeException;

final class UpdateTenantStatusController extends Controller
{
    public function __invoke(
        UpdateTenantStatusRequest $request,
        Tenant $tenant,
        UpdateTenantStatusAction $updateStatus,
    ): RedirectResponse {
        try {
            $updateStatus->handle($tenant, $request->nextStatus(), $request->reason());
        } catch (RuntimeException $exception) {
            return back()->withErrors(['status' => $exception->getMessage()]);
        }

        return redirect()
            ->route('super-admin.tenants.show', $tenant)
            ->with('success', __('Estado actualizado correctamente.'));
    }
}
