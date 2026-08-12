<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps Fortify's customer registration off the platform host.
 *
 * WHY: `/register` signs a traveller up **for the current agency**, so without a
 * tenant App\Actions\Fortify\CreateNewUser can only fail with "No se pudo
 * determinar la agencia actual". On the platform host the intent is always to
 * register an agency, which lives at `/start`.
 */
final class RedirectRegistrationToOnboarding
{
    public function handle(Request $request, Closure $next): Response
    {
        // WHY: matched by path, not route name — Fortify only names the GET route,
        // so `routeIs('register')` would let the POST through to the failing action.
        if ($request->is('register') && Tenant::current() === null) {
            return redirect()->route('onboarding.start');
        }

        return $next($request);
    }
}
