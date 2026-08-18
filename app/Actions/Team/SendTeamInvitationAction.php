<?php

declare(strict_types=1);

namespace App\Actions\Team;

use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantUserInvitationNotification;
use Illuminate\Support\Facades\Password;

/**
 * Emite el enlace de "establecé tu contraseña" con el que una persona acepta su
 * invitación a una agencia. Tres llamadores: el alta desde super admin, la invitación
 * desde el equipo y el reenvío.
 */
final class SendTeamInvitationAction
{
    public function handle(User $user, Tenant $tenant): void
    {
        $token = Password::broker()->createToken($user);

        $user->notify(TenantUserInvitationNotification::for($tenant, $token, $user->email));
    }
}
