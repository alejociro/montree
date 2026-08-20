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
     * La cuenta de escalones vive en `Locale::resolveFor()`: el handler de errores la
     * necesita para las rutas que nunca llegan a este middleware.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale(Locale::resolveFor($request));

        return $next($request);
    }
}
