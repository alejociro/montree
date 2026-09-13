<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\TourDate\RestoreTourDateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\RestoreTourDateRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\TourDate;
use Illuminate\Http\JsonResponse;

final class RestoreTourDateController extends Controller
{
    public function __construct(private RestoreTourDateAction $action) {}

    public function __invoke(RestoreTourDateRequest $request, TourDate $tourDate): JsonResponse
    {
        $restored = $this->action->handle($tourDate);

        $resource = new TourDateDetailResource($restored->load(['tour', 'guide', 'route', 'provider', 'hotels']));

        return new JsonResponse(['data' => $resource->resolve()]);
    }
}
