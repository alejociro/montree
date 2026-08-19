<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Actions\Team\SendTeamInvitationAction;
use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class CreateTenantUserAction
{
    public function __construct(private SendTeamInvitationAction $sendInvitation) {}

    /**
     * Create (or attach an existing) user to a specific tenant with the given
     * role, from the super admin context, and email them an invitation to set
     * their password.
     *
     * @param  array{name:string, email:string, role:string}  $data
     */
    public function handle(Tenant $tenant, array $data): User
    {
        $email = $data['email'];
        $role = UserRole::from($data['role']);

        $user = User::query()->where('email', $email)->first();
        $isNew = $user === null;

        if ($isNew) {
            $user = User::query()->create([
                'name' => $data['name'],
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
        }

        if (! $isNew && $tenant->users()->where('users.id', $user->id)->exists()) {
            throw TeamException::alreadyMember();
        }

        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'status' => TenantMembershipStatus::Active->value,
                'invited_at' => now(),
                'joined_at' => now(),
            ],
        ]);

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$role->value]);

        $this->sendInvitation->handle($user, $tenant);

        return $user->fresh();
    }
}
