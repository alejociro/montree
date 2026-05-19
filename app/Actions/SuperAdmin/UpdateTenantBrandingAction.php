<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

final class UpdateTenantBrandingAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data, ?UploadedFile $logo, ?UploadedFile $favicon, ?UploadedFile $heroImage): TenantConfiguration
    {
        $configuration = TenantConfiguration::query()
            ->firstOrCreate(['tenant_id' => $tenant->id]);

        $brandingPath = "tenants/{$tenant->id}/branding";
        $fileUpdates = [];

        if ($logo !== null) {
            $this->deleteIfPresent($configuration->logo_path);
            $fileUpdates['logo_path'] = $logo->store($brandingPath, 'public');
        }

        if ($favicon !== null) {
            $this->deleteIfPresent($configuration->favicon_path);
            $fileUpdates['favicon_path'] = $favicon->store($brandingPath, 'public');
        }

        if ($heroImage !== null) {
            $this->deleteIfPresent($configuration->hero_image_path);
            $fileUpdates['hero_image_path'] = $heroImage->store($brandingPath, 'public');
        }

        $fieldsToUpdate = collect($data)
            ->except(['logo', 'favicon', 'hero_image'])
            ->merge($fileUpdates)
            ->all();

        if ($fieldsToUpdate !== []) {
            $configuration->update($fieldsToUpdate);
        }

        return $configuration->fresh() ?? $configuration;
    }

    private function deleteIfPresent(?string $path): void
    {
        if ($path === null) {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
