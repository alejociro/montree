<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\SuperAdmin\UpdateGlobalCommissionScheduleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateCommissionScheduleRequest;
use Illuminate\Http\RedirectResponse;

final class UpdateGlobalCommissionScheduleController extends Controller
{
    public function __invoke(
        UpdateCommissionScheduleRequest $request,
        UpdateGlobalCommissionScheduleAction $updateSchedule,
    ): RedirectResponse {
        $updateSchedule->execute($request->tiers(), $request->maxCharge());

        return redirect()
            ->route('super-admin.commission.edit')
            ->with('success', __('Esquema global de comisión actualizado.'));
    }
}
