<?php

declare(strict_types=1);

namespace App\Http\Resources\Role;

use App\Services\Rbac\PermissionCatalog;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Role;

/**
 * @mixin Role
 */
final class RoleDetailResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Role $role */
        $role = $this->resource;

        /** @var array<int, string> $slugs */
        $slugs = $role->permissions->pluck('name')->all();

        return [
            'id' => $role->getKey(),
            'name' => $role->name,
            'label' => TenantRoleCatalog::labelFor($role),
            'is_base' => TenantRoleCatalog::isBase($role),
            'permissions' => app(PermissionCatalog::class)->describe($slugs),
        ];
    }
}
