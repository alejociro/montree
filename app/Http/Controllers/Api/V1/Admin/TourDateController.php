<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\TourDate\CreateTourDateAction;
use App\Actions\TourDate\DeleteTourDateAction;
use App\Actions\TourDate\UpdateTourDateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\StoreTourDateRequest;
use App\Http\Requests\Admin\TourDate\UpdateTourDateRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

final class TourDateController extends Controller
{
    private const RELATIONS = ['tour', 'guide', 'route', 'provider', 'hotels'];

    public function __construct(
        private CreateTourDateAction $createAction,
        private UpdateTourDateAction $updateAction,
        private DeleteTourDateAction $deleteAction,
    ) {}

    public function index(Request $request, Tour $tour): AnonymousResourceCollection
    {
        Gate::authorize('view', $tour);

        $scope = in_array($request->string('scope')->toString(), ['upcoming', 'past', 'all'], true)
            ? $request->string('scope')->toString()
            : 'upcoming';
        $perPage = min(max((int) $request->integer('per_page', 15), 1), 100);

        $dates = $tour->dates()
            ->with(self::RELATIONS)
            ->when($scope === 'upcoming', fn ($query) => $query->where('starts_at', '>', now()))
            ->when($scope === 'past', fn ($query) => $query->where('starts_at', '<=', now()))
            ->orderBy('starts_at', $scope === 'past' ? 'desc' : 'asc')
            ->paginate($perPage)
            ->withQueryString();

        return TourDateDetailResource::collection($dates);
    }

    public function store(StoreTourDateRequest $request, Tour $tour): JsonResponse
    {
        $tourDate = $this->createAction->handle($tour, $request->validated());

        return $this->respondWith($tourDate, 201);
    }

    public function update(UpdateTourDateRequest $request, TourDate $tourDate): JsonResponse
    {
        $updated = $this->updateAction->handle($tourDate, $request->validated());

        return $this->respondWith($updated);
    }

    public function destroy(TourDate $tourDate): JsonResponse
    {
        Gate::authorize('update', $tourDate->tour);

        $this->deleteAction->handle($tourDate);

        return new JsonResponse(null, 204);
    }

    private function respondWith(TourDate $tourDate, int $status = 200): JsonResponse
    {
        $resource = new TourDateDetailResource($tourDate->load(self::RELATIONS));

        return new JsonResponse(['data' => $resource->resolve()], $status);
    }
}
