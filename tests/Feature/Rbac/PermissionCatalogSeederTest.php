<?php

declare(strict_types=1);

namespace Tests\Feature\Rbac;

use App\Enums\UserRole;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

/**
 * WHY: el catálogo se escribe acá a mano, copiado de `docs/specs/F018-rbac/spec.md`
 * §"Catálogo de permisos". Si el test leyera la constante del seeder no probaría nada:
 * comprobaría que el seeder es igual a sí mismo.
 */
final class PermissionCatalogSeederTest extends TestCase
{
    use RefreshDatabase;

    /**
     * WHY: `spec.md` y `rbacbase.md` titulan el catálogo como "37 permisos" pero la lista
     * que enumeran tiene 38. Manda la enumeración; el número es un error de conteo en los
     * dos documentos y quedó anotado en `tasks.md` para `montree-spec-updater`.
     */
    public const CATALOG_SIZE = 39;

    /**
     * @var array<string, array<int, string>>
     */
    private const CATALOG = [
        'Dashboard' => ['dashboard.view', 'reports.view', 'reports.export'],
        'Productos' => ['tours.view', 'tours.create', 'tours.update', 'tours.publish', 'tours.delete', 'tours.images.manage'],
        'Salidas' => ['departures.view', 'departures.create', 'departures.update', 'departures.cancel', 'departures.delete', 'departures.assign_guide'],
        'Logistica' => ['logistics.view', 'logistics.manage'],
        'Reservas' => ['bookings.view', 'bookings.update', 'bookings.passengers.medical.view', 'payments.refund'],
        'Promociones' => ['promotions.view', 'promotions.create', 'promotions.update', 'promotions.delete'],
        'Newsletter' => ['newsletter.view', 'newsletter.send'],
        'Resenas' => ['reviews.view', 'reviews.moderate', 'reviews.respond'],
        'Equipo' => ['team.view', 'team.invite', 'team.role.update', 'team.suspend'],
        'Configuracion' => ['tenant.view', 'tenant.update', 'tenant.settings.update'],
        'Guia' => ['guide.schedule.view', 'guide.travelers.view'],
    ];

    public function test_seeder_creates_the_whole_permission_catalog_grouped_by_module(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $stored = Permission::query()->pluck('name')->all();

        foreach (self::CATALOG as $module => $permissions) {
            foreach ($permissions as $permission) {
                $this->assertContains($permission, $stored, "Falta [{$permission}] del módulo {$module}.");
            }
        }

        $this->assertCount(self::CATALOG_SIZE, $stored);
    }

    public function test_seeder_creates_the_six_base_roles_with_their_permission_sets(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertSame(count(UserRole::cases()), Role::query()->count());
        $this->assertCount(self::CATALOG_SIZE, $this->permissionsOf(UserRole::Admin));
        $this->assertSame([
            'bookings.update',
            'bookings.view',
            'dashboard.view',
            'departures.view',
            'newsletter.send',
            'newsletter.view',
            'promotions.create',
            'promotions.update',
            'promotions.view',
            'reports.view',
            'reviews.moderate',
            'reviews.respond',
            'reviews.view',
            'tours.view',
        ], $this->permissionsOf(UserRole::Sales));
        $this->assertSame([
            'dashboard.view',
            'departures.assign_guide',
            'departures.cancel',
            'departures.create',
            'departures.update',
            'departures.view',
            'logistics.manage',
            'logistics.view',
            'tours.create',
            'tours.images.manage',
            'tours.publish',
            'tours.update',
            'tours.view',
        ], $this->permissionsOf(UserRole::Operator));
        $this->assertSame([
            // D7 de tours-admin-passengers: el guía ve EPS y observaciones; `sales`, no.
            'bookings.passengers.medical.view',
            'departures.view',
            'guide.schedule.view',
            'guide.travelers.view',
            'tours.view',
        ], $this->permissionsOf(UserRole::Guide));
        $this->assertSame([], $this->permissionsOf(UserRole::Customer));
    }

    public function test_seeder_is_idempotent_and_does_not_duplicate_rows(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        $this->assertCount(self::CATALOG_SIZE, Permission::query()->pluck('name')->all());
        $this->assertSame(count(UserRole::cases()), Role::query()->count());
        $this->assertCount(self::CATALOG_SIZE, $this->permissionsOf(UserRole::Admin));
    }

    /**
     * @return array<int, string>
     */
    private function permissionsOf(UserRole $role): array
    {
        /** @var Role $model */
        $model = Role::query()->where('name', $role->value)->firstOrFail();

        return $model->permissions()->pluck('name')->sort()->values()->all();
    }
}
