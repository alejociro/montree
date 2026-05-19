<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Exceptions\FeatureRequiresEnterpriseException;
use App\Models\Tenant;
use App\Models\TenantConfiguration;
use App\Services\Tenant\CustomCssSanitizer;
use Illuminate\Support\Arr;

final class UpdateTenantConfigurationAction
{
    public function __construct(private CustomCssSanitizer $sanitizer) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Tenant $tenant, array $data): TenantConfiguration
    {
        $configuration = $tenant->configuration()->firstOrCreate(['tenant_id' => $tenant->id]);

        if (Arr::has($data, 'custom_css') && $data['custom_css'] !== null && $data['custom_css'] !== '') {
            if (! $tenant->plan->limits()['allows_custom_css']) {
                throw new FeatureRequiresEnterpriseException('custom_css');
            }

            $sanitized = $this->sanitizer->sanitize((string) $data['custom_css']);
            $data['custom_css'] = $sanitized['css'];
        }

        $configuration->fill($data);
        $configuration->save();

        return $configuration->fresh() ?? $configuration;
    }
}
