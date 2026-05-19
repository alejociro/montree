<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Resources\SuperAdmin\SuperAdminTenantResource;
use App\Models\Tenant;
use App\Services\SuperAdmin\PlatformMetricsAggregator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

final class TenantController extends Controller
{
    private const ALLOWED_SORTS = ['created_at', 'name', 'bookings_count', 'revenue'];

    public function __construct(private PlatformMetricsAggregator $aggregator) {}

    public function index(Request $request): ResourceCollection
    {
        $perPage = min((int) $request->integer('per_page', 15), 100);
        $perPage = $perPage > 0 ? $perPage : 15;

        $query = Tenant::query();

        $this->applySearch($query, $request);
        $this->applyStatusFilter($query, $request);
        $this->applyPlanFilter($query, $request);
        $this->applySort($query, $request);

        $paginator = $query->paginate($perPage);

        $paginator->getCollection()->transform(fn (Tenant $tenant) => new SuperAdminTenantResource(
            $tenant,
            $this->aggregator->statsForTenant($tenant),
        ));

        return SuperAdminTenantResource::collection($paginator);
    }

    public function show(Tenant $tenant): JsonResponse
    {
        $tenant->loadMissing('configuration');

        return new JsonResponse([
            'data' => (new SuperAdminTenantResource(
                $tenant,
                $this->aggregator->statsForTenant($tenant),
            ))->resolve(),
        ]);
    }

    private function applySearch(Builder $query, Request $request): void
    {
        $search = $request->string('search')->trim()->toString();

        if ($search === '') {
            return;
        }

        $query->where(function ($builder) use ($search): void {
            $builder->where('name', 'like', "%{$search}%")
                ->orWhere('slug', 'like', "%{$search}%");
        });
    }

    private function applyStatusFilter(Builder $query, Request $request): void
    {
        $status = $request->string('status')->trim()->toString();

        if ($status === '') {
            return;
        }

        $query->where('status', $status);
    }

    private function applyPlanFilter(Builder $query, Request $request): void
    {
        $plan = $request->string('plan')->trim()->toString();

        if ($plan === '') {
            return;
        }

        $query->where('plan', $plan);
    }

    private function applySort(Builder $query, Request $request): void
    {
        $sort = $request->string('sort', 'created_at')->toString();
        $direction = strtolower($request->string('direction', 'desc')->toString()) === 'asc' ? 'asc' : 'desc';

        if (! in_array($sort, self::ALLOWED_SORTS, true) || in_array($sort, ['bookings_count', 'revenue'], true)) {
            $sort = 'created_at';
        }

        $query->orderBy($sort, $direction);
    }
}
