<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Enums\TenantMembershipStatus;
use App\Models\TenantUser;
use Illuminate\Auth\Events\PasswordReset;

/**
 * Aceptar la invitación = fijar la contraseña con el enlace que llegó al correo.
 *
 * WHY: se activan TODAS las membresías `invited` del usuario, no solo la de la agencia
 * desde la que abrió el enlace. `invited` significa "todavía no probó que ese correo es
 * suyo"; el enlace de recuperación lo prueba, y esa prueba vale para cualquier agencia
 * que lo haya invitado a la misma dirección.
 */
final class ActivateInvitedMemberships
{
    public function handle(PasswordReset $event): void
    {
        TenantUser::query()
            ->where('user_id', $event->user->getAuthIdentifier())
            ->where('status', TenantMembershipStatus::Invited->value)
            ->update([
                'status' => TenantMembershipStatus::Active->value,
                'joined_at' => now(),
            ]);
    }
}
