<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Logistics\SaveRouteAction;
use App\Exceptions\LogisticsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreRouteRequest;
use App\Http\Requests\Admin\Logistics\UpdateRouteRequest;
use App\Http\Resources\Admin\RouteResource;
use App\Models\Route as RouteModel;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class RouteController extends Controller
{
    public function __construct(private SaveRouteAction $saveRoute) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('logistics.view');

        $routes = RouteModel::query()
            ->with('stops')
            ->withCount('tourDates')
            ->when($request->filled('search'), function (Builder $query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';

                // El buscador de la barra es uno solo para los tres catálogos y
                // promete «nombre, municipio, contacto o tarifa»: buscar solo
                // por nombre dejaba fuera justo lo que se busca a mano.
                $query->where(function (Builder $scoped) use ($term): void {
                    $scoped->where('name', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhere('state', 'like', $term)
                        ->orWhere('description', 'like', $term)
                        ->orWhereHas('stops', fn (Builder $stops) => $stops->where('name', 'like', $term));
                });
            })
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 12), 1), 100))
            ->withQueryString();

        return RouteResource::collection($routes);
    }

    public function store(StoreRouteRequest $request): JsonResponse
    {
        $route = $this->saveRoute->handle(null, $request->validated());

        return new JsonResponse(['data' => (new RouteResource($route->loadCount('tourDates')))->resolve()], 201);
    }

    public function update(UpdateRouteRequest $request, RouteModel $route): JsonResponse
    {
        $route = $this->saveRoute->handle($route, $request->validated());

        return new JsonResponse(['data' => (new RouteResource($route->loadCount('tourDates')))->resolve()]);
    }

    public function destroy(RouteModel $route): JsonResponse
    {
        Gate::authorize('logistics.manage');

        $usage = $route->tourDates()->count();

        if ($usage > 0) {
            throw LogisticsException::inUse('Ruta', $usage);
        }

        $route->delete();

        return new JsonResponse(null, 204);
    }
}
