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

        return Inertia::render('SuperAdmin/Tenant/Charges', $this->props($request, $tenant));
    }

    /**
     * @return array<string, mixed>
     */
    private function props(PlatformChargeIndexRequest $request, Tenant $tenant): array
    {
        $charges = $this->query($request, $tenant);

        return [
            'tenant' => [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
            ],
            'charges' => $this->paginated($charges->clone()),
            'filters' => $request->filters(),
            'totals' => [
                'amount' => $charges->totalAmount(),
                'count' => $charges->count(),
                'currency' => $tenant->configuration?->currency ?? 'USD',
            ],
        ];
    }

    /**
     * @param  PlatformChargeBuilder  $charges  Recibe una copia: paginar le pega
     *                                          `limit`/`offset` al builder y los
     *                                          totales son sobre toda la selección.
     * @return array<string, mixed>
     */
    private function paginated(PlatformChargeBuilder $charges): array
    {
        $paginator = $charges
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
