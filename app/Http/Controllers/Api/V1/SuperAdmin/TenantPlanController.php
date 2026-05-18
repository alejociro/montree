<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\SuperAdmin\UpdateTenantPlanAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantPlanRequest;
use App\Http\Resources\SuperAdmin\SuperAdminTenantResource;
use App\Models\Tenant;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Http\JsonResponse;

final class TenantPlanController extends Controller
{
    public function __construct(
        private UpdateTenantPlanAction $action,
        private PlatformMetricsAggregator $aggregator,
    ) {}

    public function update(UpdateTenantPlanRequest $request, Tenant $tenant): JsonResponse
    {
        $updated = $this->action->handle($tenant, $request->newPlan());

        return new JsonResponse([
            'data' => (new SuperAdminTenantResource(
                $updated,
                $this->aggregator->statsForTenant($updated),
            ))->resolve(),
        ]);
    }
}
