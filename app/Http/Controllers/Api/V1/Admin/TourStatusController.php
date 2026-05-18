<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\Tour\ChangeTourStatusAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Tour\ChangeTourStatusRequest;
use App\Http\Resources\Tour\TourResource;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

final class TourStatusController extends Controller
{
    public function __construct(private ChangeTourStatusAction $action) {}

    public function __invoke(ChangeTourStatusRequest $request, Tour $tour): JsonResponse
    {
        $updated = $this->action->handle($tour, $request->nextStatus());

        return new JsonResponse(['data' => (new TourResource($updated))->resolve()]);
    }
}
