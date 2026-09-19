<?php

declare(strict_types=1);

namespace App\Actions\Role;

use App\Exceptions\RoleException;
use App\Services\Rbac\PermissionCatalog;
use App\Services\Rbac\TenantRoleCatalog;
use Spatie\Permission\Models\Role;

final class UpdateTenantRoleAction
{
    public function __construct(private PermissionCatalog $catalog) {}

    /**
     * @param  array{name?: string, description?: string|null, permissions?: array<int, string>}  $data
     */
    public function handle(Role $role, array $data): Role
    {
        if (TenantRoleCatalog::isBase($role)) {
            throw RoleException::baseRoleIsReadOnly();
        }

        if (isset($data['name'])) {
            $role->update(['name' => trim($data['name'])]);
        }

        // `array_key_exists` y no `isset`: mandar `null` es borrar la descripción.
        if (array_key_exists('description', $data)) {
            $role->update(['description' => CreateTenantRoleAction::description($data['description'])]);
        }

        if (isset($data['permissions'])) {
            $role->syncPermissions([...$data['permissions'], ...$this->hiddenPermissions($role)]);
        }

        return $role->load('permissions');
    }

    /**
     * Permisos del rol que pertenecen a un módulo apagado.
     *
     * WHY: la pantalla de roles no los muestra, así que tampoco los manda de
     * vuelta. Sin esto, editar el nombre de un rol borraría lo que ese rol tenía
     * concedido en el módulo apagado, y encenderlo de nuevo no lo devolvería.
     *
     * @return array<int, string>
     */
    private function hiddenPermissions(Role $role): array
    {
        $hidden = array_flip($this->catalog->disabledSlugs());

        return $role->permissions
            ->pluck('name')
            ->filter(static fn (string $name): bool => isset($hidden[$name]))
            ->values()
            ->all();
    }
}
