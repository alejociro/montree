<?php

declare(strict_types=1);

namespace App\Actions\Settings;

use App\Models\User;

final class UpdateLocaleAction
{
    /**
     * Persiste el idioma elegido en la cuenta. La cookie la encola el controller:
     * es transporte HTTP, no dominio.
     */
    public function handle(?User $user, string $locale): void
    {
        $user?->forceFill(['locale' => $locale])->save();
    }
}
