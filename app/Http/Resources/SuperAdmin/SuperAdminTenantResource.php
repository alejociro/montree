<?php

declare(strict_types=1);

namespace App\Http\Resources\SuperAdmin;

use App\Enums\Currency;
use App\Http\Resources\TenantConfigurationResource;
use App\Models\CommissionSchedule;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class SuperAdminTenantResource extends JsonResource
{
    /**
     * @param  array{bookings_count_30d: int, revenue_30d: string, charges_30d: string}|null  $stats
     */
    public function __construct(Tenant $tenant, private readonly ?array $stats = null)
    {
        parent::__construct($tenant);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $ownSchedule = $this->whenLoaded('commissionSchedule');

        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'domain' => $this->domain,
            'status' => $this->status->value,
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'created_at' => $this->created_at?->toIso8601String(),
            'can_enter' => $this->resource->canBeEntered(),
            'commission' => [
                'scope' => $ownSchedule instanceof CommissionSchedule ? 'tenant' : 'global',
                'tiers_count' => $ownSchedule instanceof CommissionSchedule ? count($ownSchedule->tiers) : null,
                'currency' => $this->resource->configuration?->currency ?? Currency::FALLBACK,
            ],
            'stats' => [
                'users_count' => (int) ($this->users_count ?? 0),
                'tours_count' => (int) ($this->tours_count ?? 0),
                'bookings_count_30d' => $this->stats['bookings_count_30d'] ?? 0,
                'revenue_30d' => $this->stats['revenue_30d'] ?? '0.00',
                'charges_30d' => $this->stats['charges_30d'] ?? '0.00',
            ],
            'configuration' => $this->whenLoaded('configuration', fn (): array => (new TenantConfigurationResource($this->resource->configuration))->resolve()),
        ];
    }
}
