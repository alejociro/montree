<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Data\BrandingAssetsData;
use App\Models\TenantConfiguration;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * Guarda logo, favicon e imagen principal en el disco `public` y deja los
 * paths en la configuración. La comparten el panel del tenant y el super admin.
 */
final class StoreBrandingAssetsAction
{
    public function execute(TenantConfiguration $configuration, BrandingAssetsData $assets): TenantConfiguration
    {
        if (! $assets->touchesAnything()) {
            return $configuration;
        }

        $directory = "tenants/{$configuration->tenant_id}/branding";
        $paths = [];

        if ($assets->removeLogo && $assets->logo === null) {
            $this->deleteIfPresent($configuration->logo_path);
            $paths['logo_path'] = null;
        }

        if ($assets->removeHeroImage && $assets->heroImage === null) {
            $this->deleteIfPresent($configuration->hero_image_path);
            $paths['hero_image_path'] = null;
        }

        $paths += array_filter([
            'logo_path' => $this->replace($configuration->logo_path, $assets->logo, $directory),
            'favicon_path' => $this->replace($configuration->favicon_path, $assets->favicon, $directory),
            'hero_image_path' => $this->replace($configuration->hero_image_path, $assets->heroImage, $directory),
        ], static fn (?string $path): bool => $path !== null);

        $configuration->forceFill($paths)->save();

        return $configuration;
    }

    private function replace(?string $currentPath, ?UploadedFile $file, string $directory): ?string
    {
        if ($file === null) {
            return null;
        }

        $this->deleteIfPresent($currentPath);

        return $file->store($directory, 'public') ?: null;
    }

    private function deleteIfPresent(?string $path): void
    {
        if ($path === null || $path === '') {
            return;
        }

        Storage::disk('public')->delete($path);
    }
}
