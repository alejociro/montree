<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Data\TenantConfigurationData;
use App\Enums\PlaceToPayEnvironment;
use App\Models\TenantConfiguration;
use App\Services\Tenant\CustomCssSanitizer;
use Illuminate\Support\Arr;

final class UpdateTenantConfigurationAction
{
    public function __construct(private CustomCssSanitizer $sanitizer) {}

    public function execute(TenantConfiguration $configuration, TenantConfigurationData $data): TenantConfiguration
    {
        $attributes = $this->resolveCustomCss($configuration, $data->attributes);
        $attributes = $this->resolveCheckoutCredentials($configuration, $attributes);

        $configuration->fill($attributes);
        $configuration->save();

        return $configuration;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveCustomCss(TenantConfiguration $configuration, array $attributes): array
    {
        if (blank($attributes['custom_css'] ?? null)) {
            return $attributes;
        }

        // WHY: el CSS personalizado está disponible para todas las agencias
        // (ya no hay planes); igual se sanea siempre, sin excepción.
        $attributes['custom_css'] = $this->sanitizer->sanitize((string) $attributes['custom_css'])['css'];

        return $attributes;
    }

    /**
     * Vaciar el login apaga el comercio propio del tenant (vuelve al de
     * plataforma). Mandar login sin tranKey conserva el guardado: el panel nunca
     * recibe el tranKey de vuelta, así que no puede reenviarlo.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function resolveCheckoutCredentials(TenantConfiguration $configuration, array $attributes): array
    {
        if (! Arr::hasAny($attributes, ['placetopay_login', 'placetopay_tran_key', 'placetopay_environment'])) {
            return $attributes;
        }

        if (blank($attributes['placetopay_login'] ?? null)) {
            return array_merge($attributes, [
                'placetopay_login' => null,
                'placetopay_tran_key' => null,
                'placetopay_environment' => PlaceToPayEnvironment::Test,
            ]);
        }

        if (blank($attributes['placetopay_tran_key'] ?? null)) {
            $attributes['placetopay_tran_key'] = $configuration->placetopay_tran_key;
        }

        return $attributes;
    }
}
