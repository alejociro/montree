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

        $data = $this->resolveCheckoutCredentials($configuration, $data);

        $configuration->fill($data);
        $configuration->save();

        return $configuration->fresh() ?? $configuration;
    }

    /**
     * Vaciar el login apaga el comercio propio del tenant (vuelve al de
     * plataforma). Mandar login sin tranKey conserva el guardado: el panel nunca
     * recibe el tranKey de vuelta, así que no puede reenviarlo.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function resolveCheckoutCredentials(TenantConfiguration $configuration, array $data): array
    {
        if (! Arr::hasAny($data, ['placetopay_login', 'placetopay_tran_key', 'placetopay_url'])) {
            return $data;
        }

        if (blank($data['placetopay_login'] ?? null)) {
            return array_merge($data, [
                'placetopay_login' => null,
                'placetopay_tran_key' => null,
                'placetopay_url' => null,
            ]);
        }

        if (blank($data['placetopay_tran_key'] ?? null)) {
            $data['placetopay_tran_key'] = $configuration->placetopay_tran_key;
        }

        return $data;
    }
}
