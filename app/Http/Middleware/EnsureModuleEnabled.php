<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\Module;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureModuleEnabled
{
    /**
     * WHY: 404 y no 403. Un 403 confirma que la pantalla existe y que al usuario
     * solo le falta permiso; un módulo apagado no está en el producto.
     */
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(Module::from($module)->isEnabled(), Response::HTTP_NOT_FOUND);

        return $next($request);
    }
}
