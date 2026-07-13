<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Exceptions\LogisticsException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Logistics\StoreProviderRequest;
use App\Http\Requests\Admin\Logistics\UpdateProviderRequest;
use App\Http\Resources\Admin\ProviderResource;
use App\Models\Provider;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class ProviderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $providers = Provider::query()
            ->withCount('tourDates')
            ->when($request->filled('search'), fn ($query) => $query->where('name', 'like', '%'.$request->string('search')->toString().'%'))
            ->orderBy('name')
            ->paginate(min(max((int) $request->integer('per_page', 15), 1), 100))
            ->withQueryString();

        return ProviderResource::collection($providers);
    }

    public function store(StoreProviderRequest $request): JsonResponse
    {
        $provider = Provider::create($request->validated());

        return new JsonResponse(['data' => (new ProviderResource($provider->loadCount('tourDates')))->resolve()], 201);
    }

    public function update(UpdateProviderRequest $request, Provider $provider): JsonResponse
    {
        $provider->update($request->validated());

        return new JsonResponse(['data' => (new ProviderResource($provider->loadCount('tourDates')))->resolve()]);
    }

    public function destroy(Provider $provider): JsonResponse
    {
        $usage = $provider->tourDates()->count();

        if ($usage > 0) {
            throw LogisticsException::inUse('Proveedor', $usage);
        }

        $provider->delete();

        return new JsonResponse(null, 204);
    }
}
