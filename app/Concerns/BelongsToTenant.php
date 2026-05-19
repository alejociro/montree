<?php

declare(strict_types=1);

namespace App\Concerns;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

/**
 * Single-DB multi-tenancy via `tenant_id` column.
 *
 * - Adds a global scope that filters every query by the current tenant.
 * - Auto-fills `tenant_id` on `creating` so application code never sets it manually.
 * - Throws if a tenant-scoped write happens without a current tenant.
 *
 * @property int $tenant_id
 *
 * @mixin Model
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', static function (Builder $builder): void {
            $tenant = Tenant::current();

            if ($tenant === null) {
                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenant->getKey());
        });

        static::creating(static function (Model $model): void {
            if ($model->getAttribute('tenant_id') !== null) {
                return;
            }

            $tenant = Tenant::current();

            if ($tenant === null) {
                throw new RuntimeException(sprintf(
                    'Cannot create [%s] without a current tenant. Call Tenant::makeCurrent() first.',
                    $model::class,
                ));
            }

            $model->setAttribute('tenant_id', $tenant->getKey());
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
