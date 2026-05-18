<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Team\InviteMemberAction;
use App\Actions\Team\UpdateMemberRoleAction;
use App\Actions\Team\UpdateMemberStatusAction;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Team\InviteMemberRequest;
use App\Http\Requests\Admin\Team\UpdateMemberRoleRequest;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class TeamController extends Controller
{
    public function __construct(
        private InviteMemberAction $invite,
        private UpdateMemberRoleAction $updateRole,
        private UpdateMemberStatusAction $updateStatus,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $tenant = Tenant::current();
        $members = $tenant->users()->withPivot(['status', 'joined_at', 'suspended_at'])->get();

        $payload = $members->map(function (User $u) use ($tenant) {
            setPermissionsTeamId($tenant->id);
            $u->unsetRelation('roles');
            $role = $u->getRoleNames()->first();

            return [
                'id' => $u->id,
                'name' => $u->name,
                'email' => $u->email,
                'role' => $role,
                'status' => $u->pivot->status,
                'joined_at' => $u->pivot->joined_at,
            ];
        });

        return new JsonResponse(['data' => $payload]);
    }

    public function store(InviteMemberRequest $request): JsonResponse
    {
        $user = $this->invite->handle($request->validated(), Tenant::current());

        return new JsonResponse(['data' => ['id' => $user->id, 'email' => $user->email]], Response::HTTP_CREATED);
    }

    public function updateRole(UpdateMemberRoleRequest $request, User $user): JsonResponse
    {
        $tenant = Tenant::current();
        $this->updateRole->handle($tenant, $user, UserRole::from($request->validated('role')));

        return new JsonResponse(['data' => ['id' => $user->id, 'role' => $request->validated('role')]]);
    }

    public function suspend(Request $request, User $user): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }
        $this->updateStatus->handle(Tenant::current(), $user, TenantMembershipStatus::Suspended);

        return new JsonResponse(['data' => ['id' => $user->id, 'status' => 'suspended']]);
    }

    public function reactivate(Request $request, User $user): JsonResponse
    {
        if (! $request->user()?->hasRole('admin')) {
            abort(403);
        }
        $this->updateStatus->handle(Tenant::current(), $user, TenantMembershipStatus::Active);

        return new JsonResponse(['data' => ['id' => $user->id, 'status' => 'active']]);
    }
}
