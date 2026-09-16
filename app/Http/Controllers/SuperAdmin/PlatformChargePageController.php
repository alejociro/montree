<?php

declare(strict_types=1);

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\PlatformChargeIndexRequest;
use App\Http\Resources\SuperAdmin\PlatformChargeResource;
use App\Models\Builders\PlatformChargeBuilder;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformChargePageController extends Controller
{
    private const PER_PAGE = 25;

    public function index(PlatformChargeIndexRequest $request, Tenant $tenant): Response
    {
        $tenant->loadMissing('configuration');

        return Inertia::render('SuperAdmin/Tenant/Charges', [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'charges' => $this->charges($request, $tenant),
            'filters' => $request->filters(),
            'totals' => [
                'amount' => $this->query($request, $tenant)->totalAmount(),
                'count' => $this->query($request, $tenant)->count(),
                'currency' => $tenant->configuration?->currency ?? 'USD',
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function charges(PlatformChargeIndexRequest $request, Tenant $tenant): array
    {
        $paginator = $this->query($request, $tenant)
            ->with('booking')
            ->orderByDesc('charged_at')
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return PlatformChargeResource::collection($paginator)
            ->response()
            ->getData(assoc: true);
    }

    private function query(PlatformChargeIndexRequest $request, Tenant $tenant): PlatformChargeBuilder
    {
        return PlatformCharge::query()
            ->forTenant($tenant->id)
            ->chargedBetween($request->from(), $request->to());
    }
}
