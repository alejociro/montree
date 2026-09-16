<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\PlatformMetricsResource;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Carbon\CarbonImmutable;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminDashboardController extends Controller
{
    public function __invoke(PlatformMetricsAggregator $aggregator): Response
    {
        $now = CarbonImmutable::now();

        $metrics = $aggregator->collect($now->startOfMonth(), $now->endOfMonth());

        return Inertia::render('SuperAdmin/Dashboard', [
            ...(new PlatformMetricsResource($metrics))->resolve(),
            'currency' => (string) config('montree.platform_currency'),
        ]);
    }
}
