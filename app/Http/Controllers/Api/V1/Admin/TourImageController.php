<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Tour\AttachTourImageAction;
use App\Actions\Tour\DetachTourImageAction;
use App\Actions\Tour\UpdateTourImageAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\StoreTourImageRequest;
use App\Http\Requests\Admin\Tour\UpdateTourImageRequest;
use App\Http\Resources\Tour\TourImageResource;
use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Http\JsonResponse;

final class TourImageController extends Controller
{
    public function __construct(
        private AttachTourImageAction $attachAction,
        private UpdateTourImageAction $updateAction,
        private DetachTourImageAction $detachAction,
    ) {}

    public function store(StoreTourImageRequest $request, Tour $tour): JsonResponse
    {
        $image = $this->attachAction->handle(
            $tour,
            $request->file('image'),
            (bool) $request->boolean('is_cover'),
            $request->input('alt_text'),
        );

        return new JsonResponse(['data' => (new TourImageResource($image))->resolve()], 201);
    }

    public function update(UpdateTourImageRequest $request, Tour $tour, TourImage $image): JsonResponse
    {
        abort_if($image->tour_id !== $tour->id, 404);

        $updated = $this->updateAction->handle($image, $request->validated());

        return new JsonResponse(['data' => (new TourImageResource($updated))->resolve()]);
    }

    public function destroy(Tour $tour, TourImage $image): JsonResponse
    {
        abort_if($image->tour_id !== $tour->id, 404);

        $this->detachAction->handle($image);

        return new JsonResponse(null, 204);
    }
}
