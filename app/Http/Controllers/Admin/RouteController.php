<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Logistics\SaveRouteAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreRouteRequest;
use App\Http\Requests\Admin\Logistics\UpdateRouteRequest;
use App\Models\Route as RouteModel;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class RouteController extends Controller
{
    public function store(StoreRouteRequest $request, SaveRouteAction $saveRoute): RedirectResponse
    {
        $saveRoute->execute(null, $request->validated());

        return back()->with('success', __('Ruta creada.'));
    }

    public function update(UpdateRouteRequest $request, RouteModel $route, SaveRouteAction $saveRoute): RedirectResponse
    {
        $saveRoute->execute($route, $request->validated());

        return back()->with('success', __('Ruta actualizada.'));
    }

    public function destroy(RouteModel $route): RedirectResponse
    {
        Gate::authorize('logistics.manage');

        $blocker = $this->blockingUsage($route);

        if ($blocker !== null) {
            return back()->withErrors(['route' => $blocker]);
        }

        $route->delete();

        return back()->with('success', __('Ruta eliminada.'));
    }

    /**
     * Una ruta en uso no se borra: se nombra quién la usa. Los productos pesan
     * tanto como las salidas —desasociarla de un producto es una decisión del
     * catálogo, no un efecto colateral de vaciar logística—.
     */
    private function blockingUsage(RouteModel $route): ?string
    {
        $tours = $route->tours()->orderBy('name')->pluck('tours.name')->all();
        $departures = $route->tourDates()->count();

        if ($tours !== []) {
            return __('No se puede eliminar: la ruta está asociada a :tours.', ['tours' => implode(', ', $tours)]);
        }

        if ($departures > 0) {
            return trans_choice(
                '{1}No se puede eliminar: la ruta está en uso por :count salida.|[2,*]No se puede eliminar: la ruta está en uso por :count salidas.',
                $departures,
                ['count' => $departures],
            );
        }

        return null;
    }
}
