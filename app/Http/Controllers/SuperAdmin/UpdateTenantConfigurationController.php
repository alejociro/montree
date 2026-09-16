<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Actions\Tenant\StoreBrandingAssetsAction;
use App\Data\BrandingAssetsData;
use App\Data\TenantConfigurationData;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateTenantConfigurationRequest;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Http\RedirectResponse;

final class UpdateTenantConfigurationController extends Controller
{
    public function __invoke(
        UpdateTenantConfigurationRequest $request,
        Tenant $tenant,
        StoreBrandingAssetsAction $storeAssets,
    ): RedirectResponse {
        $configuration = TenantConfiguration::query()->firstOrCreate(['tenant_id' => $tenant->id]);

        $configuration->fill(TenantConfigurationData::fromRequest($request)->attributes)->save();

        $storeAssets->execute($configuration, BrandingAssetsData::fromRequest($request));

        return redirect()
            ->route('super-admin.tenants.show', $tenant)
            ->with('success', __('Configuración actualizada correctamente.'));
    }
}
