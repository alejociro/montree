<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserRole;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesAndPermissionsSeeder extends Seeder
{
    public const GUARD = 'web';

    /**
     * Catálogo de permisos `modulo.accion` agrupado por módulo (F018 spec.md).
     *
     * @var array<string, array<int, string>>
     */
    public const PERMISSIONS = [
        'dashboard' => ['dashboard.view', 'reports.view', 'reports.export'],
        'tours' => ['tours.view', 'tours.create', 'tours.update', 'tours.publish', 'tours.delete', 'tours.images.manage'],
        'departures' => ['departures.view', 'departures.create', 'departures.update', 'departures.cancel', 'departures.delete', 'departures.assign_guide'],
        'logistics' => ['logistics.view', 'logistics.manage'],
        'bookings' => ['bookings.view', 'bookings.update', 'payments.refund'],
        'promotions' => ['promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete'],
        'newsletter' => ['newsletter.view', 'newsletter.send'],
        'reviews' => ['reviews.view', 'reviews.moderate', 'reviews.respond'],
        'team' => ['team.view', 'team.invite', 'team.role.update', 'team.suspend'],
        'tenant' => ['tenant.view', 'tenant.update', 'tenant.settings.update'],
        'guide' => ['guide.schedule.view', 'guide.travelers.view'],
    ];

    /**
     * Matriz rol → permiso (F018 spec.md §"Matriz rol × permiso").
     * `admin` no se lista: recibe el catálogo completo.
     *
     * @var array<string, array<int, string>>
     */
    private const ROLE_PERMISSIONS = [
        'sales' => [
            'dashboard.view',
            'reports.view',
            'tours.view',
            'departures.view',
            'bookings.view',
            'bookings.update',
            'promotions.view',
            'promotions.create',
            'promotions.update',
            'newsletter.view',
            'newsletter.send',
            'reviews.view',
            'reviews.moderate',
            'reviews.respond',
        ],
        'operator' => [
            'dashboard.view',
            'tours.view',
            'tours.create',
            'tours.update',
            'tours.publish',
            'tours.images.manage',
            'departures.view',
            'departures.create',
            'departures.update',
            'departures.cancel',
            'departures.assign_guide',
            'logistics.view',
            'logistics.manage',
        ],
        'guide' => [
            'tours.view',
            'departures.view',
            'guide.schedule.view',
            'guide.travelers.view',
        ],
        'customer' => [],
    ];

    public function run(): void
    {
        $this->forgetCachedPermissions();

        $this->seedRoles();
        $this->seedPermissions();
        $this->seedRolePermissions();
        $this->grantSalesToExistingOperators();

        $this->forgetCachedPermissions();
    }

    /**
     * @return array<int, string>
     */
    public static function permissionNames(): array
    {
        return array_merge(...array_values(self::PERMISSIONS));
    }

    private function seedRoles(): void
    {
        foreach (UserRole::cases() as $role) {
            Role::query()->updateOrCreate(
                ['name' => $role->value, 'guard_name' => self::GUARD, 'tenant_id' => null],
            );
        }
    }

    private function seedPermissions(): void
    {
        foreach (self::permissionNames() as $permission) {
            Permission::query()->updateOrCreate(
                ['name' => $permission, 'guard_name' => self::GUARD],
            );
        }
    }

    private function seedRolePermissions(): void
    {
        $this->forgetCachedPermissions();

        $this->roleByName(UserRole::Admin)->syncPermissions(self::permissionNames());

        foreach (self::ROLE_PERMISSIONS as $roleName => $permissions) {
            $this->roleByName(UserRole::from($roleName))->syncPermissions($permissions);
        }
    }

    /**
     * Corte de `operator` en `sales` + `operator`: nadie pierde acceso el día del
     * despliegue. Idempotente — la PK compuesta de model_has_roles descarta duplicados.
     */
    private function grantSalesToExistingOperators(): void
    {
        $table = (string) config('permission.table_names.model_has_roles');
        $teamKey = (string) config('permission.column_names.team_foreign_key');
        // WHY: estas claves vienen nulas en config/permission.php cuando se usa el default
        // del paquete, así que `config(..., $default)` no alcanza: la clave existe.
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
        $modelKey = (string) (config('permission.column_names.model_morph_key') ?? 'model_id');

        $salesRoleId = $this->roleByName(UserRole::Sales)->getKey();

        $assignments = DB::table($table)
            ->where($roleKey, $this->roleByName(UserRole::Operator)->getKey())
            ->get();

        foreach ($assignments as $assignment) {
            DB::table($table)->insertOrIgnore([
                $roleKey => $salesRoleId,
                'model_type' => $assignment->model_type,
                $modelKey => $assignment->{$modelKey},
                $teamKey => $assignment->{$teamKey},
            ]);
        }
    }

    private function roleByName(UserRole $role): Role
    {
        /** @var Role $model */
        $model = Role::query()
            ->where('name', $role->value)
            ->where('guard_name', self::GUARD)
            ->whereNull('tenant_id')
            ->firstOrFail();

        return $model;
    }

    private function forgetCachedPermissions(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
