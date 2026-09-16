<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tour\DeleteRouteAction;
use App\Actions\Tour\SaveRouteAction;
use App\Data\Tour\RouteData;
use App\Exceptions\LogisticsRecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\StoreRouteRequest;
use App\Http\Requests\Admin\Tour\UpdateRouteRequest;
use App\Models\Route;
use App\Models\Tour;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

final class TourRouteController extends Controller
{
    public function store(StoreRouteRequest $request, Tour $tour, SaveRouteAction $saveRoute): RedirectResponse
    {
        $saveRoute->execute($tour, RouteData::fromRequest($request));

        return back()->with('success', __('Ruta creada.'));
    }

    public function update(UpdateRouteRequest $request, Route $route, SaveRouteAction $saveRoute): RedirectResponse
    {
        $saveRoute->execute($route->tour, RouteData::fromRequest($request), $route);

        return back()->with('success', __('Ruta actualizada.'));
    }

    public function destroy(Route $route, DeleteRouteAction $deleteRoute): RedirectResponse
    {
        Gate::authorize('update', $route->tour);

        try {
            $deleteRoute->execute($route);
        } catch (LogisticsRecordInUseException $inUse) {
            return back()->withErrors(['route' => $inUse->getMessage()]);
        }

        return back()->with('success', __('Ruta eliminada.'));
    }
}
