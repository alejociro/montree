<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\Locale;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class SetLocale
{
    /**
     * Fija el idioma del request. Corre en el grupo `web` después de ResolveTenant y
     * antes de HandleInertiaRequests, que comparte el catálogo ya resuelto.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale($this->resolve($request));

        return $next($request);
    }

    /**
     * Cuenta explícita: preferencia del usuario, cookie, navegador, default.
     * Un valor fuera del catálogo no aborta — cae al siguiente escalón.
     */
    private function resolve(Request $request): string
    {
        $user = $request->user();

        if ($user !== null && Locale::isSupported($user->locale)) {
            return (string) $user->locale;
        }

        $cookie = $request->cookie('locale');

        if (is_string($cookie) && Locale::isSupported($cookie)) {
            return $cookie;
        }

        $preferred = $request->getPreferredLanguage(Locale::supported());

        if (is_string($preferred) && Locale::isSupported($preferred)) {
            return $preferred;
        }

        return Locale::default();
    }
}
