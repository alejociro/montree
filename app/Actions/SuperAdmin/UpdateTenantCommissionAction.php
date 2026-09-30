<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Models\CommissionSchedule;
use App\Models\Tenant;

final class UpdateTenantCommissionAction
{
    /**
     * `$tiers === null` (o `$useGlobal` true) borra el esquema propio de la
     * agencia: vuelve a caer en el global. Con rangos, se guardan en la
     * moneda de configuración de la agencia (spec: "el esquema está en la
     * moneda de la agencia").
     *
     * @param  array<int, array{from: string, to: string|null, rate: string}>|null  $tiers
     */
    public function execute(Tenant $tenant, bool $useGlobal, ?array $tiers, ?string $maxCharge): void
    {
        if ($useGlobal || $tiers === null) {
            CommissionSchedule::query()->where('tenant_id', $tenant->id)->delete();

            return;
        }

        $tenant->loadMissing('configuration');
        $currency = $tenant->configuration?->currency ?? 'COP';

        CommissionSchedule::query()->updateOrCreate(
            ['tenant_id' => $tenant->id],
            ['currency' => $currency, 'tiers' => $tiers, 'max_charge' => $maxCharge],
        );
    }
}
