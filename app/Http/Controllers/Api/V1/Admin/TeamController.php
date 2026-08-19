<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Team\InviteMemberAction;
use App\Actions\Team\ResendInvitationAction;
use App\Actions\Team\UpdateMemberRoleAction;
use App\Actions\Team\UpdateMemberStatusAction;
use App\Enums\TenantMembershipStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\InviteMemberRequest;
use App\Http\Requests\Admin\Team\TeamIndexRequest;
use App\Http\Requests\Admin\Team\UpdateMemberRoleRequest;
use App\Http\Resources\Team\TeamMemberResource;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Rbac\TenantRoleCatalog;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class TeamController extends Controller
{
    public function __construct(
        private InviteMemberAction $invite,
        private UpdateMemberRoleAction $updateRole,
        private UpdateMemberStatusAction $updateStatus,
        private ResendInvitationAction $resendInvitation,
        private TenantRoleCatalog $roles,
    ) {}

    public function index(TeamIndexRequest $request): AnonymousResourceCollection
    {
        $tenant = Tenant::current();
        setPermissionsTeamId($tenant->id);

        $members = $tenant->users()
            ->withPivot(['status', 'invited_at', 'joined_at', 'suspended_at'])
            ->with('roles')
            ->whereHas('roles', fn (Builder $query) => $query->whereIn('name', $this->roles->assignableNames($tenant)))
            // WHY: `tenant_user.status` calificado y no `wherePivot()` — dentro de un
            // `when()` la relación entrega el query builder, y ahí `wherePivot` cae en el
            // manejo de `whereXxx` dinámico (filtra por una columna llamada "pivot").
            ->when($request->status(), fn (Builder $query, TenantMembershipStatus $status) => $query->where('tenant_user.status', $status->value))
            ->when($request->role(), fn (Builder $query, string $role) => $query->whereHas('roles', fn (Builder $roles) => $roles->where('name', $role)))
            ->when($request->search(), $this->searchByNameOrEmail(...))
            ->orderBy('users.name')
            ->paginate($request->perPage())
            ->withQueryString();

        return TeamMemberResource::collection($members);
    }

    public function store(InviteMemberRequest $request): JsonResponse
    {
        $user = $this->invite->handle($request->validated(), Tenant::current());

        return new JsonResponse(['data' => ['id' => $user->id, 'email' => $user->email]], Response::HTTP_CREATED);
    }

    public function updateRole(UpdateMemberRoleRequest $request, User $user): JsonResponse
    {
        $roles = $request->validatedRoles();
        $this->updateRole->handle(Tenant::current(), $user, $roles);

        return new JsonResponse(['data' => ['id' => $user->id, 'roles' => $roles]]);
    }

    public function resend(User $user): JsonResponse
    {
        $this->resendInvitation->handle(Tenant::current(), $user);

        return new JsonResponse(['data' => ['id' => $user->id, 'status' => TenantMembershipStatus::Invited->value]]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()?->id) {
            abort(422, 'No puedes suspenderte a ti mismo.');
        }

        $this->updateStatus->handle(Tenant::current(), $user, TenantMembershipStatus::Suspended);

        return new JsonResponse(['data' => ['id' => $user->id, 'status' => 'suspended']]);
    }

    public function reactivate(User $user): JsonResponse
    {
        $this->updateStatus->handle(Tenant::current(), $user, TenantMembershipStatus::Active);

        return new JsonResponse(['data' => ['id' => $user->id, 'status' => 'active']]);
    }

    /**
     * @param  Builder<User>  $query
     */
    private function searchByNameOrEmail(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(fn (Builder $match) => $match
            ->where('users.name', 'like', $like)
            ->orWhere('users.email', 'like', $like));
    }
}
