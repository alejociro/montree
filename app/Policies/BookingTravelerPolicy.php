<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\BookingTraveler;
use App\Models\User;

/**
 * Autorización de la planilla de pasajeros.
 *
 * WHY: el acceso del guía **no** pasa por aquí sino por la comprobación
 * explícita `tourDate.guide_id === auth()->id()` en su zona: es pertenencia,
 * no permiso (plan.md §D1). Lo que sí vive aquí es `viewMedical`, la única
 * definición de quién ve EPS y observaciones (D7): la consultan el Resource
 * —el punto donde se enmascara la serialización—, el filtro por segmento, el
 * resumen, el CSV y la máscara de escritura.
 */
final class BookingTravelerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('bookings.view');
    }

    public function create(User $user): bool
    {
        return $user->can('bookings.update');
    }

    public function update(User $user, BookingTraveler $passenger): bool
    {
        return $user->can('bookings.update');
    }

    public function viewMedical(User $user): bool
    {
        return $user->can('bookings.passengers.medical.view');
    }
}
