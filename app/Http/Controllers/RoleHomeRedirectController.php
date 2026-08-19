<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Models\User;
use App\Services\Auth\RoleHomeResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * WHY: F018 A3 — `/dashboard` redirigía fijo a `/account/bookings`, así que admin,
 * vendedor, operador y guía aterrizaban en la zona de viajero. Usa el mismo resolutor
 * que el login para que haya un solo lugar donde se decide el home de cada usuario.
 */
final class RoleHomeRedirectController extends Controller
{
    public function __construct(private RoleHomeResolver $roleHome) {}

    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $tenant = Tenant::current();

        abort_if($tenant === null, 403);

        return redirect()->to($this->roleHome->homeFor($user, $tenant));
    }
}
