<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
class TenantResource extends JsonResource
{
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
            'contact_email' => $this->contact_email,
            'contact_phone' => $this->contact_phone,
        ];
    }
}
