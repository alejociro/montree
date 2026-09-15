<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Http\Requests\Admin\Dashboard\DashboardRequest;
use App\Http\Resources\Admin\DashboardResource;
use App\Models\Tenant;
use App\Policies\DashboardPolicy;
use App\Services\Dashboard\DashboardMetricsAggregator;
use App\Services\Dashboard\PeriodFilter;
use Inertia\Inertia;
use Inertia\Response;

/**
 * El panel de la agencia. El aislamiento por tenant lo dan los global scopes de
 * los modelos que agregan las métricas.
 */
final class DashboardPagesController extends Controller
{
    public function __construct(private DashboardMetricsAggregator $aggregator) {}

    public function __invoke(DashboardRequest $request): Response
    {
        $tenant = Tenant::current();

        abort_if($tenant === null, 404, __('No tenant for this host.'));

        $period = PeriodFilter::fromKey(
            $request->periodKey(),
            $request->timezone($tenant->configuration?->timezone ?? config('app.timezone')),
        );

        return Inertia::render('Admin/Dashboard', [
            'snapshot' => $this->snapshot($request, $tenant, $period),
            'periods' => PeriodFilter::options(),
            'filters' => ['period' => $period->key],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(DashboardRequest $request, Tenant $tenant, PeriodFilter $period): array
    {
        $canExport = (new DashboardPolicy)->exportReports($request->user());

        return (new DashboardResource($this->aggregator->for($tenant, $period), $canExport))->resolve();
    }
}
