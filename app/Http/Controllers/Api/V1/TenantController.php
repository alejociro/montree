<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\TenantConfigurationResource;
use App\Http\Resources\TenantResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

final class TenantController extends Controller
{
    public function show(): JsonResponse
    {
        $tenant = Tenant::current();

        if ($tenant === null) {
            return new JsonResponse([
                'message' => 'No tenant for this host.',
                'error_code' => 'TENANT_NOT_RESOLVED',
            ], 404);
        }

        $tenant->loadMissing('configuration');

        return new JsonResponse([
            'data' => [
                'tenant' => (new TenantResource($tenant))->resolve(),
                'configuration' => $tenant->configuration !== null
                    ? (new TenantConfigurationResource($tenant->configuration))->resolve()
                    : null,
            ],
        ]);
    }
}
