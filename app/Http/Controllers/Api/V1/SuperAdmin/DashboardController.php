<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\PlatformMetricsResource;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

final class DashboardController extends Controller
{
    public function __construct(private PlatformMetricsAggregator $aggregator) {}

    public function show(): JsonResponse
    {
        $from = Carbon::now()->startOfMonth();
        $to = Carbon::now()->endOfMonth();

        $metrics = Cache::remember(
            sprintf('super-admin:dashboard:%s:%s', $from->toDateString(), $to->toDateString()),
            60,
            fn () => $this->aggregator->collect($from, $to),
        );

        return new JsonResponse([
            'data' => (new PlatformMetricsResource($metrics))->resolve(),
        ]);
    }
}
