<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\SuperAdmin\UpdateTenantStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantStatusRequest;
use App\Http\Resources\SuperAdmin\SuperAdminTenantResource;
use App\Models\Tenant;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Http\JsonResponse;
use RuntimeException;

final class TenantStatusController extends Controller
{
    public function __construct(
        private UpdateTenantStatusAction $action,
        private PlatformMetricsAggregator $aggregator,
    ) {}

    public function update(UpdateTenantStatusRequest $request, Tenant $tenant): JsonResponse
    {
        try {
            $updated = $this->action->handle($tenant, $request->nextStatus(), $request->reason());
        } catch (RuntimeException $exception) {
            return new JsonResponse([
                'message' => $exception->getMessage(),
                'error_code' => 'TENANT_STATUS_UNCHANGED',
            ], 409);
        }

        return new JsonResponse([
            'data' => (new SuperAdminTenantResource(
                $updated,
                $this->aggregator->statsForTenant($updated),
            ))->resolve(),
        ]);
    }
}
