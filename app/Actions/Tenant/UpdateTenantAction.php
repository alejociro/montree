<?php

declare(strict_types=1);

namespace App\Actions\Tenant;

use App\Models\Tenant;

final class UpdateTenantAction
{
    /**
     * @param  array{name?: string, contact_email?: string, contact_phone?: ?string}  $data
     */
    public function handle(Tenant $tenant, array $data): Tenant
    {
        $tenant->fill($data);
        $tenant->save();

        return $tenant->fresh() ?? $tenant;
    }
}
