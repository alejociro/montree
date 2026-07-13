<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Actions\TourDate\CancelTourDateAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\TourDate\CancelTourDateRequest;
use App\Http\Resources\Admin\TourDateDetailResource;
use App\Models\TourDate;
use Illuminate\Http\JsonResponse;

final class CancelTourDateController extends Controller
{
    public function __construct(private CancelTourDateAction $action) {}

    public function __invoke(CancelTourDateRequest $request, TourDate $tourDate): JsonResponse
    {
        $cancelled = $this->action->handle($tourDate, $request->validated('reason'));

        $resource = new TourDateDetailResource($cancelled->load(['tour', 'guide', 'route', 'provider', 'hotels']));

        return new JsonResponse(['data' => $resource->resolve()]);
    }
}
