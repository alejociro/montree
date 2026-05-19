<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class InviteMemberAction
{
    /**
     * Invite a user (existing or new) to the current tenant with the given role.
     *
     * @param  array{email:string, name?:string, role:string}  $data
     */
    public function handle(array $data, Tenant $tenant): User
    {
        $email = mb_strtolower((string) $data['email']);
        $role = UserRole::from($data['role']);

        $user = User::query()->where('email', $email)->first();
        $isNew = false;

        if ($user === null) {
            $user = User::query()->create([
                'name' => $data['name'] ?? explode('@', $email)[0],
                'email' => $email,
                'password' => Hash::make(Str::random(40)),
            ]);
            $isNew = true;
        }

        $alreadyMember = $tenant->users()->where('users.id', $user->id)->exists();
        if ($alreadyMember && ! $isNew) {
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

        return $user->fresh();
    }
}
