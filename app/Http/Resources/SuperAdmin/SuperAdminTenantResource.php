<?php

declare(strict_types=1);

namespace App\Http\Resources\SuperAdmin;

use App\Http\Resources\TenantConfigurationResource;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class SuperAdminTenantResource extends JsonResource
{
    /**
     * @param  array{users_count?: int, tours_count?: int, bookings_count_30d?: int, revenue_30d?: string}|null  $stats
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
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'domain' => $this->domain,
            'status' => $this->status->value,
            'plan' => $this->plan->value,
            'trial_ends_at' => $this->trial_ends_at?->toIso8601String(),
            'suspended_at' => $this->suspended_at?->toIso8601String(),
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
            'users_count' => $this->stats['users_count'] ?? null,
            'tours_count' => $this->stats['tours_count'] ?? null,
            'bookings_count_30d' => $this->stats['bookings_count_30d'] ?? null,
            'revenue_30d' => $this->stats['revenue_30d'] ?? null,
            'created_at' => $this->created_at?->toIso8601String(),
            'configuration' => $this->whenLoaded('configuration', function () {
                return (new TenantConfigurationResource($this->configuration))->resolve();
            }),
        ];
    }
}
