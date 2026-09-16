<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Logistics\DeleteRouteAction;
use App\Actions\Logistics\SaveRouteAction;
use App\Exceptions\LogisticsRecordInUseException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreRouteRequest;
use App\Http\Requests\Admin\Logistics\UpdateRouteRequest;
use App\Models\Route as RouteModel;
use Illuminate\Http\RedirectResponse;

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

    public function destroy(RouteModel $route, DeleteRouteAction $deleteRoute): RedirectResponse
    {
        try {
            $deleteRoute->execute($route);
        } catch (LogisticsRecordInUseException $inUse) {
            return back()->withErrors(['route' => $inUse->getMessage()]);
        }

        return back()->with('success', __('Ruta eliminada.'));
    }
}
