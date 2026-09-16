<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tour\SetDefaultRouteAction;
use App\Http\Controllers\Controller;
use App\Models\Route;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class DefaultRouteController extends Controller
{
    public function __invoke(Route $route, SetDefaultRouteAction $setDefault): RedirectResponse
    {
        Gate::authorize('update', $route->tour);

        $setDefault->execute($route);

        return back()->with('success', __('Ruta predeterminada actualizada.'));
    }
}
