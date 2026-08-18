<?php

declare(strict_types=1);

namespace App\Services\Rbac;

use App\Enums\UserRole;
use App\Models\Tenant;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Permission\Models\Role;

/**
 * Roles que una agencia ve y puede asignar: los base staff-asignables
 * (`tenant_id = null`) más los propios de la agencia (`tenant_id = <tenant>`).
 *
 * WHY: la misma pregunta la hacen el listado de equipo, la validación de
 * `UpdateMemberRoleRequest`, el módulo de roles y sus resources (regla del 3).
 */
final class TenantRoleCatalog
{
    /**
     * Roles base que el equipo de una agencia puede asignar. `super_admin` es global
     * (vive en el team sentinel 0) y `customer` no es un rol de equipo.
     *
     * @var array<int, string>
     */
    public const STAFF_ROLES = [
        UserRole::Admin->value,
        UserRole::Sales->value,
        UserRole::Operator->value,
        UserRole::Guide->value,
    ];

    /**
     * @return Builder<Role>
     */
    public function visibleQuery(Tenant $tenant): Builder
    {
        return Role::query()
            ->where('guard_name', RolesAndPermissionsSeeder::GUARD)
            ->where(fn (Builder $query): Builder => $query
                ->where(fn (Builder $base): Builder => $base
                    ->whereNull('tenant_id')
                    ->whereIn('name', self::STAFF_ROLES))
                ->orWhere('tenant_id', $tenant->getKey()))
            ->orderBy('tenant_id')
            ->orderBy('id');
    }

    /**
     * @return array<int, string>
     */
    public function assignableNames(Tenant $tenant): array
    {
        /** @var array<int, string> $names */
        $names = $this->visibleQuery($tenant)->pluck('name')->all();

        return $names;
    }

    public static function isBase(Role $role): bool
    {
        return $role->tenant_id === null;
    }

    /**
     * WHY: la comparación va en minúsculas a mano y no con `Rule::unique` porque MySQL
     * compara sin distinguir mayúsculas (utf8mb4_unicode_ci) y SQLite —la BD de la suite—
     * sí las distingue: sin esto, "Ventas" y "ventas" convivirían solo en los tests.
     */
    public function nameTaken(Tenant $tenant, string $name, ?int $exceptId = null): bool
    {
        return Role::query()
            ->where('tenant_id', $tenant->getKey())
            ->whereRaw('lower(name) = ?', [mb_strtolower(trim($name))])
            ->when($exceptId !== null, fn (Builder $query): Builder => $query->whereKeyNot($exceptId))
            ->exists();
    }

    /**
     * Nombres que un rol propio no puede usar: los de los 6 roles base, aunque no todos
     * sean asignables desde el equipo — un rol propio llamado `customer` o `super_admin`
     * confundiría a `UserRole` y al resolutor de roles de spatie.
     */
    public static function isReservedName(string $name): bool
    {
        return UserRole::tryFrom(mb_strtolower(trim($name))) !== null;
    }

    public static function labelFor(Role $role): string
    {
        if (! self::isBase($role)) {
            return $role->name;
        }

        return UserRole::tryFrom($role->name)?->label() ?? $role->name;
    }
}
