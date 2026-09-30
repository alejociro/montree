<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Data\SuperAdmin\TenantFilters;
use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\TenantIndexRequest;
use App\Http\Resources\SuperAdmin\SuperAdminTenantResource;
use App\Models\Tenant;
use App\Services\Platform\CommissionScheduleResolver;
use App\Services\Rbac\TenantRoleCatalog;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

final class SuperAdminTenantPageController extends Controller
{
    private const PER_PAGE = 15;

    public function __construct(
        private PlatformMetricsAggregator $aggregator,
        private CommissionScheduleResolver $commissionResolver,
    ) {}

    public function index(TenantIndexRequest $request): Response
    {
        $filters = TenantFilters::fromRequest($request);

        return Inertia::render('SuperAdmin/Tenant/Index', [
            'tenants' => $this->paginated($filters),
            'filters' => $filters->toArray(),
        ]);
    }

    public function show(Tenant $tenant): Response
    {
        $tenant->loadMissing(['configuration', 'commissionSchedule'])->loadCount(['users', 'tours']);

        $stats = $this->aggregator->statsForTenants([$tenant->id]);
        $resolved = $this->commissionResolver->resolve($tenant);

        return Inertia::render('SuperAdmin/Tenant/Detail', [
            'tenant' => (new SuperAdminTenantResource($tenant, $stats[$tenant->id]))->resolve(),
            'charges_summary' => $this->aggregator->chargesSummaryForTenant($tenant),
            'monthly' => $this->aggregator->monthlyForTenant($tenant),
            'roles' => TenantRoleCatalog::STAFF_ROLES,
            'commissionSchedule' => [
                'scope' => $resolved['scope'],
                'currency' => $resolved['schedule']->currency,
                'tiers' => $resolved['schedule']->tiers,
                'max_charge' => $resolved['schedule']->max_charge,
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function paginated(TenantFilters $filters): array
    {
        $paginator = $this->query($filters);
        $stats = $this->aggregator->statsForTenants($paginator->getCollection()->pluck('id')->all());

        $paginator->setCollection($paginator->getCollection()->map(
            fn (Tenant $tenant) => new SuperAdminTenantResource($tenant, $stats[$tenant->id]),
        ));

        return SuperAdminTenantResource::collection($paginator)
            ->response()
            ->getData(assoc: true);
    }

    /**
     * @return LengthAwarePaginator<int, Tenant>
     */
    private function query(TenantFilters $filters): LengthAwarePaginator
    {
        return Tenant::query()
            ->with(['configuration', 'commissionSchedule'])
            ->withCount(['users', 'tours'])
            ->applyFilters($filters)
            ->paginate(self::PER_PAGE)
            ->withQueryString();
    }
}
