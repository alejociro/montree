<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Tenant\UpdateTenantConfigurationAction;
use App\Exceptions\FeatureRequiresEnterpriseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tenant\UpdateTenantConfigurationRequest;
use App\Http\Resources\TenantConfigurationResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

final class TenantConfigurationController extends Controller
{
    public function __construct(private UpdateTenantConfigurationAction $action) {}

    public function update(UpdateTenantConfigurationRequest $request): JsonResponse
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404, 'No tenant for this host.');

        try {
            $configuration = $this->action->handle($tenant, $request->validated());
        } catch (FeatureRequiresEnterpriseException $exception) {
            return $exception->toResponse();
        }

        return new JsonResponse([
            'data' => [
                'configuration' => (new TenantConfigurationResource($configuration))->resolve(),
            ],
        ]);
    }
}
