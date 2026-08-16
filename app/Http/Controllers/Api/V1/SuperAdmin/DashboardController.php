<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\PlatformMetricsResource;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

final class DashboardController extends Controller
{
    public function __construct(private PlatformMetricsAggregator $aggregator) {}

    public function show(): JsonResponse
    {
        $metrics = $this->aggregator->collect(
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        );

        return new JsonResponse([
            'data' => (new PlatformMetricsResource($metrics))->resolve(),
        ]);
    }
}
