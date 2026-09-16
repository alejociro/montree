<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Actions\Tenant\StoreBrandingAssetsAction;
use App\Data\BrandingAssetsData;
use App\Data\TenantConfigurationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantConfigurationRequest;
use App\Http\Resources\TenantConfigurationResource;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Http\JsonResponse;

final class TenantConfigurationController extends Controller
{
    public function update(
        UpdateTenantConfigurationRequest $request,
        Tenant $tenant,
        StoreBrandingAssetsAction $storeAssets,
    ): JsonResponse {
        $configuration = TenantConfiguration::query()->firstOrCreate(['tenant_id' => $tenant->id]);

        $configuration->fill(TenantConfigurationData::fromRequest($request)->attributes)->save();

        $storeAssets->execute($configuration, BrandingAssetsData::fromRequest($request));

        return new JsonResponse([
            'data' => (new TenantConfigurationResource($configuration->refresh()))->resolve(),
        ]);
    }
}
