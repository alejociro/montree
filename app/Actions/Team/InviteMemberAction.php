<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Enums\TenantMembershipStatus;
use App\Exceptions\TeamException;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

final class InviteMemberAction
{
    public function __construct(private SendTeamInvitationAction $sendInvitation) {}

    /**
     * Invite a user (existing or new) to the current tenant with the given role.
     *
     * @param  array{email:string, name?:string, role:string}  $data
     */
    public function handle(array $data, Tenant $tenant): User
    {
        $email = mb_strtolower((string) $data['email']);

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

        // WHY: quien nunca fijó una contraseña no puede entrar todavía — su membresía
        // queda `invited` hasta que acepte (ver ActivateInvitedMemberships). Quien ya
        // tiene cuenta se suma activo: no hay nada que aceptar.
        $pending = $user->mustSetPassword();

        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'status' => $pending
                    ? TenantMembershipStatus::Invited->value
                    : TenantMembershipStatus::Active->value,
                'invited_at' => now(),
                'joined_at' => $pending ? null : now(),
            ],
        ]);

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$data['role']]);

        if ($pending) {
            $this->sendInvitation->handle($user, $tenant);
        }

        return $user->fresh();
    }
}
