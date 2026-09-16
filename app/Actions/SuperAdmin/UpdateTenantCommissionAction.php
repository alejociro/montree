<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\CommissionType;
use App\Models\Tenant;

final class UpdateTenantCommissionAction
{
    public function execute(Tenant $tenant, ?CommissionType $type, ?string $value): Tenant
    {
        $tenant->update([
            'commission_type' => $type,
            'commission_value' => $type === null ? null : $value,
        ]);

        return $tenant->refresh();
    }
}
