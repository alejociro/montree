<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\TenantConfiguration;
use App\Services\Tenant\HexToHsl;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin TenantConfiguration
 */
class TenantConfigurationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'primary_color' => $this->primary_color,
            'primary_color_hsl' => HexToHsl::convert($this->primary_color),
            'secondary_color' => $this->secondary_color,
            'secondary_color_hsl' => HexToHsl::convert($this->secondary_color),
            'logo_url' => $this->resolveUrl($this->logo_path),
            'favicon_url' => $this->resolveUrl($this->favicon_path),
            'currency' => $this->currency,
            'timezone' => $this->timezone,
            'locale' => $this->locale,
            'tagline' => $this->tagline,
            'description' => $this->description,
            'social_links' => $this->social_links,
            'contact_info' => $this->contact_info,
            'reviews_require_moderation' => (bool) $this->reviews_require_moderation,
            'require_traveler_details' => (bool) $this->require_traveler_details,
            'custom_css' => $this->custom_css,
            'hero_image_url' => $this->resolveUrl($this->hero_image_path),
            'min_partial_payment_pct' => $this->min_partial_payment_pct,
        ];
    }

    private function resolveUrl(?string $path): ?string
    {
        if ($path === null || $path === '') {
            return null;
        }

        if (str_starts_with($path, 'http://') || str_starts_with($path, 'https://')) {
            return $path;
        }

        return Storage::disk(config('filesystems.default'))->url($path);
    }
}
