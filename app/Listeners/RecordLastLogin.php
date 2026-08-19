<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\Request;

final class RecordLastLogin
{
    public function __construct(private Request $request) {}

    public function handle(Login $event): void
    {
        // WHY: un login de tenant dispara DOS eventos Login (multi-tenancy.md §10.1.1):
        // el del host donde se validó la contraseña y el de `auth.handoff`, que solo
        // traslada esa sesión al host destino. El segundo no es un acceso nuevo; contarlo
        // pisaría la marca con la misma visita partida en dos.
        if ($this->request->routeIs('auth.handoff')) {
            return;
        }

        if (! $event->user instanceof User) {
            return;
        }

        // WHY: forceFill — `last_login_at` no es fillable a propósito (ver User::casts).
        $event->user->forceFill(['last_login_at' => now()])->save();
    }
}
