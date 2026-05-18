<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\Dashboard\DashboardRequest;
use App\Http\Resources\Admin\DashboardResource;
use App\Models\Tenant;
use App\Policies\DashboardPolicy;
use App\Services\Dashboard\DashboardMetricsAggregator;
use App\Services\Dashboard\PeriodFilter;
use Illuminate\Http\JsonResponse;

final class DashboardController extends Controller
{
    public function __construct(private DashboardMetricsAggregator $aggregator) {}

    public function show(DashboardRequest $request): JsonResponse
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404, 'No tenant for this host.');

        $timezone = $request->timezone($tenant->configuration?->timezone ?? config('app.timezone'));
        $period = PeriodFilter::fromKey($request->periodKey(), $timezone);
        $snapshot = $this->aggregator->for($tenant, $period);
        $canExport = (new DashboardPolicy)->exportReports($request->user());

        return new JsonResponse([
            'data' => (new DashboardResource($snapshot, $canExport))->resolve(),
        ]);
    }
}
