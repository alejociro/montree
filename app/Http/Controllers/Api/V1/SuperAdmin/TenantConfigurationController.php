<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\SuperAdmin\UpdateTenantBrandingAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantConfigurationRequest;
use App\Http\Resources\TenantConfigurationResource;
use App\Models\Tenant;
use Illuminate\Http\JsonResponse;

final class TenantConfigurationController extends Controller
{
    public function __construct(private UpdateTenantBrandingAction $action) {}

    public function update(UpdateTenantConfigurationRequest $request, Tenant $tenant): JsonResponse
    {
        $configuration = $this->action->handle(
            $tenant,
            $request->safe()->except(['logo', 'favicon', 'hero_image']),
            $request->file('logo'),
            $request->file('favicon'),
            $request->file('hero_image'),
        );

        return new JsonResponse([
            'data' => (new TenantConfigurationResource($configuration))->resolve(),
        ]);
    }
}
