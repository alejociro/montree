<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Data\GuideAvailability;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Guide\GuideAvailabilityRequest;
use App\Queries\GuideAvailabilityQuery;
use Illuminate\Http\JsonResponse;

/**
 * Los guías del tenant con los días que ya tienen ocupados en el rango. Alimenta
 * el select del panel para que no ofrezca lo que la regla va a rechazar (D9).
 */
final class GuideAvailabilityController extends Controller
{
    public function __construct(private GuideAvailabilityQuery $query) {}

    public function __invoke(GuideAvailabilityRequest $request): JsonResponse
    {
        $guides = $this->query->handle(
            $request->from(),
            $request->to(),
            $request->excludeTourDateId(),
        );

        return new JsonResponse([
            'data' => $guides->map(static fn (GuideAvailability $guide): array => $guide->toArray())->values(),
        ]);
    }
}
