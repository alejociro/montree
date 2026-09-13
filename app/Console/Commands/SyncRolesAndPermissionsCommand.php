<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * Publica el catálogo de permisos y la matriz rol → permiso en cualquier entorno
 * (incluida producción) sin arrastrar datos demo, y repara las asignaciones de
 * rol de una agencia.
 *
 * WHY: `docker/entrypoint.sh` solo corre `migrate`, así que un despliegue nuevo
 * queda con `role_has_permissions` vacío: `getAllPermissions()` devuelve `[]` y
 * el menú del panel se muestra en blanco. `db:seed` a secas no sirve como
 * remedio porque `DatabaseSeeder` arrastra también `DemoTenantSeeder`.
 */
final class SyncRolesAndPermissionsCommand extends Command
{
    protected $signature = 'montree:sync-permissions
        {--tenant=* : Slug de la agencia a revisar y reparar (repetible). Omitir para revisarlas todas}
        {--assign=* : Asignación explícita email:rol para miembros que quedaron sin rol}
        {--dry-run : Muestra lo que haría sin escribir nada}';

    protected $description = 'Sincroniza roles, permisos y la matriz rol→permiso, y repara las asignaciones de rol por agencia';

    private bool $dryRun = false;

    public function handle(): int
    {
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->components->warn('Modo simulación: no se escribe nada en la base de datos.');
        }

        $this->syncCatalog();

        $tenants = $this->resolveTenants();

        if ($tenants === null) {
            return self::FAILURE;
        }

        foreach ($tenants as $tenant) {
            $this->auditTenant($tenant);
        }

        $this->forgetCachedPermissions();

        $this->newLine();
        $this->components->info('Listo. La caché de permisos de spatie quedó invalidada.');

        return self::SUCCESS;
    }

    /**
     * Roles base, catálogo de 38 permisos y matriz rol → permiso.
     */
    private function syncCatalog(): void
    {
        $this->components->info('Catálogo de roles y permisos');

        if ($this->dryRun) {
            $this->components->twoColumnDetail(
                'permisos del catálogo',
                '<fg=gray>'.count(RolesAndPermissionsSeeder::permissionNames()).' (simulación)</>',
            );

            return;
        }

        $seeder = new RolesAndPermissionsSeeder;
        $seeder->setContainer(App::getFacadeRoot());
        $seeder->setCommand($this);
        $seeder->run();

        $this->components->twoColumnDetail('permisos', '<fg=green>'.DB::table('permissions')->count().'</>');
        $this->components->twoColumnDetail('roles', '<fg=green>'.DB::table('roles')->count().'</>');
        $this->components->twoColumnDetail('rol → permiso', '<fg=green>'.DB::table('role_has_permissions')->count().'</>');
    }

    /**
     * @return Collection<int, Tenant>|null
     */
    private function resolveTenants(): ?Collection
    {
        /** @var array<int, string> $slugs */
        $slugs = (array) $this->option('tenant');

        $tenants = Tenant::query()
            ->when($slugs !== [], fn ($query) => $query->whereIn('slug', $slugs))
            ->orderBy('id')
            ->get();

        if ($tenants->isEmpty()) {
            if ($slugs === []) {
                // Despliegue recien creado: el catalogo ya quedo publicado y no
                // hay nada que auditar. No es un fallo.
                $this->components->warn('No hay agencias registradas todavía.');

                return collect();
            }

            $this->components->error('No existe ninguna agencia con slug ['.implode(', ', $slugs).'].');

            return null;
        }

        if ($slugs !== []) {
            $missing = array_diff($slugs, $tenants->pluck('slug')->all());

            if ($missing !== []) {
                $this->components->warn('Sin coincidencia para: '.implode(', ', $missing));
            }
        }

        return $tenants;
    }

    /**
     * Audita los miembros de una agencia y aplica las asignaciones pedidas.
     *
     * WHY: no hay nada que "adoptar" — `model_has_roles.tenant_id` es NOT NULL y
     * parte de la clave primaria (`create_permission_tables`), así que una
     * asignación sin agencia no puede existir. El único hueco posible es el
     * miembro de `tenant_user` sin ninguna fila de rol en su agencia, y ese se
     * repara a mano con `--assign`.
     */
    private function auditTenant(Tenant $tenant): void
    {
        $this->newLine();
        $this->components->info("Agencia [$tenant->slug] — $tenant->name");

        $table = (string) config('permission.table_names.model_has_roles');
        $teamKey = (string) config('permission.column_names.team_foreign_key');
        $roleKey = (string) (config('permission.column_names.role_pivot_key') ?? 'role_id');
        $modelKey = (string) (config('permission.column_names.model_morph_key') ?? 'model_id');
        $morphClass = (new User)->getMorphClass();

        $memberIds = $tenant->users()->pluck('users.id');

        if ($memberIds->isEmpty()) {
            $this->components->warn('La agencia no tiene miembros.');

            return;
        }

        $this->applyManualAssignments($tenant, $memberIds->all());
        $this->reportMembers($tenant, $table, $teamKey, $roleKey, $modelKey, $morphClass);
    }

    /**
     * `--assign=correo@agencia.com:admin` para el miembro que quedó sin rol.
     *
     * @param  array<int, int>  $memberIds
     */
    private function applyManualAssignments(Tenant $tenant, array $memberIds): void
    {
        /** @var array<int, string> $assignments */
        $assignments = (array) $this->option('assign');

        foreach ($assignments as $assignment) {
            [$email, $role] = array_pad(explode(':', $assignment, 2), 2, null);

            if ($email === null || $role === null) {
                $this->components->error("Formato inválido en --assign=$assignment (se espera correo:rol).");

                continue;
            }

            $user = User::query()->where('email', $email)->first();

            if ($user === null || ! in_array($user->id, $memberIds, true)) {
                $this->components->error("[$email] no es miembro de [$tenant->slug]; se omite.");

                continue;
            }

            if (! DB::table('roles')->where('name', $role)->whereNull('tenant_id')->exists()) {
                $this->components->error("El rol [$role] no existe; se omite.");

                continue;
            }

            if ($this->dryRun) {
                $this->components->twoColumnDetail($email, "<fg=gray>→ $role (simulación)</>");

                continue;
            }

            setPermissionsTeamId($tenant->id);
            $user->unsetRelation('roles');
            $user->syncRoles([$role]);

            $this->components->twoColumnDetail($email, "<fg=green>→ $role</>");
        }
    }

    private function reportMembers(
        Tenant $tenant,
        string $table,
        string $teamKey,
        string $roleKey,
        string $modelKey,
        string $morphClass,
    ): void {
        $rows = DB::table('tenant_user')
            ->join('users', 'users.id', '=', 'tenant_user.user_id')
            ->leftJoin($table, function ($join) use ($table, $teamKey, $modelKey, $morphClass, $tenant) {
                $join->on("$table.$modelKey", '=', 'users.id')
                    ->where("$table.model_type", '=', $morphClass)
                    ->where("$table.$teamKey", '=', $tenant->id);
            })
            ->leftJoin('roles', 'roles.id', '=', "$table.$roleKey")
            ->where('tenant_user.tenant_id', $tenant->id)
            ->orderBy('users.email')
            ->get(['users.email', 'roles.name as role']);

        $byUser = [];

        foreach ($rows as $row) {
            $byUser[$row->email][] = $row->role;
        }

        foreach ($byUser as $email => $roles) {
            $named = array_values(array_filter($roles));

            $this->components->twoColumnDetail(
                (string) $email,
                $named === [] ? '<fg=red>SIN ROL</>' : '<fg=green>'.implode(', ', $named).'</>',
            );
        }
    }

    private function forgetCachedPermissions(): void
    {
        App::make(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
