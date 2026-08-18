<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Role\CreateTenantRoleAction;
use App\Actions\Role\DeleteTenantRoleAction;
use App\Actions\Role\UpdateTenantRoleAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Role\StoreRoleRequest;
use App\Http\Requests\Admin\Role\UpdateRoleRequest;
use App\Http\Resources\Role\RoleDetailResource;
use App\Http\Resources\Role\RoleResource;
use App\Models\Tenant;
use App\Services\Rbac\PermissionCatalog;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;

/**
 * Roles y permisos de una agencia. Sin Policy propia: el gate es uniforme
 * (`can:team.role.update` en la ruta, F018 contracts.md §1) y `Role` es un modelo del
 * paquete, no del dominio — mismo criterio que logística y reportes en la fase 1. Lo
 * único que no es "tener el permiso" —un rol base es de solo lectura— es regla de
 * negocio y vive en las Actions como excepción de dominio.
 */
final class RoleController extends Controller
{
    public function __construct(
        private CreateTenantRoleAction $createRole,
        private UpdateTenantRoleAction $updateRole,
        private DeleteTenantRoleAction $deleteRole,
        private TenantRoleCatalog $catalog,
        private PermissionCatalog $permissions,
    ) {}

    public function index(): JsonResponse
    {
        $tenant = Tenant::current();

        $roles = $this->catalog->visibleQuery($tenant)
            ->withCount([
                'permissions',
                // WHY: los roles base los comparten todas las agencias; sin scopear el
                // conteo, `admin` mostraría los admins de la plataforma entera.
                'users' => fn (Builder $query) => $query->where('model_has_roles.tenant_id', $tenant->getKey()),
            ])
            ->get();

        return new JsonResponse([
            'data' => RoleResource::collection($roles)->resolve(),
            'meta' => ['available_permissions' => $this->permissions->all()],
        ]);
    }

    public function show(Role $role): JsonResponse
    {
        return new JsonResponse(['data' => (new RoleDetailResource($role->load('permissions')))->resolve()]);
    }

    public function store(StoreRoleRequest $request): JsonResponse
    {
        $role = $this->createRole->handle(Tenant::current(), $request->validated());

        return new JsonResponse(['data' => (new RoleDetailResource($role))->resolve()], Response::HTTP_CREATED);
    }

    public function update(UpdateRoleRequest $request, Role $role): JsonResponse
    {
        $role = $this->updateRole->handle($role, $request->validated());

        return new JsonResponse(['data' => (new RoleDetailResource($role))->resolve()]);
    }

    public function destroy(Role $role): JsonResponse
    {
        $this->deleteRole->handle(Tenant::current(), $role);

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
