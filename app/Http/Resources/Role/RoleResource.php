<?php

declare(strict_types=1);

namespace App\Http\Resources\Role;

use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * Item del listado de roles. `users_count` cuenta solo asignaciones de la agencia
 * actual (los roles base son compartidos por todas), así que el controller lo carga
 * con un `withCount` scopeado.
 *
 * @mixin Role
 */
final class RoleResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'label' => TenantRoleCatalog::labelFor($role),
            'is_base' => TenantRoleCatalog::isBase($role),
            'permissions_count' => (int) ($role->permissions_count ?? 0),
            'users_count' => (int) ($role->users_count ?? 0),
        ];
    }
}
