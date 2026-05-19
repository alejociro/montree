<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Tour\CreateTourAction;
use App\Actions\Tour\DeleteTourAction;
use App\Actions\Tour\UpdateTourAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\StoreTourRequest;
use App\Http\Requests\Admin\Tour\UpdateTourRequest;
use App\Http\Resources\Tour\TourResource;
use App\Http\Resources\Tour\TourSummaryResource;
use App\Models\Tenant;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class TourController extends Controller
{
    private const SORTABLE_COLUMNS = ['created_at', 'name', 'base_price', 'status'];

    public function __construct(
        private CreateTourAction $createAction,
        private UpdateTourAction $updateAction,
        private DeleteTourAction $deleteAction,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Tour::class);

        $sort = in_array($request->string('sort')->toString(), self::SORTABLE_COLUMNS, true)
            ? $request->string('sort')->toString()
            : 'created_at';
        $direction = $request->string('direction')->toString() === 'asc' ? 'asc' : 'desc';
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $tours = Tour::query()
            ->with(['category', 'coverImage'])
            ->withCount(['images', 'bookings'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->when($request->filled('category_id'), fn ($query) => $query->where('category_id', $request->integer('category_id')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $term = '%'.$request->string('search')->toString().'%';
                $query->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('description', 'like', $term));
            })
            ->orderBy($sort, $direction)
            ->paginate($perPage)
            ->withQueryString();

        return TourSummaryResource::collection($tours);
    }

    public function show(Tour $tour): JsonResponse
    {
        Gate::authorize('view', $tour);

        $tour->load(['category', 'images', 'itineraries']);

        return new JsonResponse(['data' => (new TourResource($tour))->resolve()]);
    }

    public function store(StoreTourRequest $request): JsonResponse
    {
        $tenant = Tenant::current();
        abort_if($tenant === null, 404);

        $tour = $this->createAction->handle($tenant, $request->validated());

        return new JsonResponse(['data' => (new TourResource($tour))->resolve()], 201);
    }

    public function update(UpdateTourRequest $request, Tour $tour): JsonResponse
    {
        $tour = $this->updateAction->handle($tour, $request->validated());

        return new JsonResponse(['data' => (new TourResource($tour))->resolve()]);
    }

    public function destroy(Tour $tour): JsonResponse
    {
        Gate::authorize('delete', $tour);

        $this->deleteAction->handle($tour);

        return new JsonResponse(null, 204);
    }
}
