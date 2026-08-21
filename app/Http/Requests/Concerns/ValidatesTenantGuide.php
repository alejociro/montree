<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use Closure;

/**
 * «Guía» no es cualquier usuario con id: es un miembro activo del tenant con el
 * rol `guide`. Lo comprueban la salida (los tres caminos) y el guía por defecto
 * del tour, así que la comprobación vive en un solo sitio.
 */
trait ValidatesTenantGuide
{
    protected function tenantId(): int
    {
        return Tenant::current()?->getKey() ?? 0;
    }

    protected function guideRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            $tenant = Tenant::current();

            if ($tenant === null) {
                $fail(__('El guía seleccionado no es válido.'));

                return;
            }

            setPermissionsTeamId($tenant->getKey());

            $isGuideMember = $tenant->users()
                ->where('users.id', $value)
                ->wherePivot('status', TenantMembershipStatus::Active->value)
                ->whereHas('roles', fn ($query) => $query->where('name', UserRole::Guide->value))
                ->exists();

            if (! $isGuideMember) {
                $fail(__('El guía seleccionado no es válido.'));
            }
        };
    }
}
