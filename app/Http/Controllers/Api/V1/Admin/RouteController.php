<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\LogisticsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreRouteRequest;
use App\Http\Requests\Admin\Logistics\UpdateRouteRequest;
use App\Http\Resources\Admin\RouteResource;
use App\Models\Route as RouteModel;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class RouteController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $routes = RouteModel::query()
            ->withCount('tourDates')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return RouteResource::collection($routes);
    }

    public function store(StoreRouteRequest $request): JsonResponse
    {
        $route = RouteModel::create($request->validated());

        return new JsonResponse(['data' => (new RouteResource($route->loadCount('tourDates')))->resolve()], 201);
    }

    public function update(UpdateRouteRequest $request, RouteModel $route): JsonResponse
    {
        $route->update($request->validated());

        return new JsonResponse(['data' => (new RouteResource($route->loadCount('tourDates')))->resolve()]);
    }

    public function destroy(RouteModel $route): JsonResponse
    {
        $usage = $route->tourDates()->count();

        if ($usage > 0) {
            throw LogisticsException::inUse('Ruta', $usage);
        }

        $route->delete();

        return new JsonResponse(null, 204);
    }
}
