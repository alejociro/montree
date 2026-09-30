<?php

declare(strict_types=1);

namespace App\Services\Platform;

use App\Models\CommissionSchedule;
use App\Models\Tenant;

/**
 * Qué esquema de comisión le aplica a una agencia: el propio si tiene uno,
 * si no el global.
 */
final class CommissionScheduleResolver
{
    /**
     * @return array{schedule: CommissionSchedule, scope: 'tenant'|'global'}
     */
    public function resolve(Tenant $tenant): array
    {
        $own = CommissionSchedule::forTenantOnly($tenant->id);

        if ($own !== null) {
            return ['schedule' => $own, 'scope' => 'tenant'];
        }

        return ['schedule' => CommissionSchedule::global(), 'scope' => 'global'];
    }
}
