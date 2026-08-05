<?php

declare(strict_types=1);

namespace App\Http\Resources\Onboarding;

use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class AgencyRegistrationResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'tenant' => [
                'slug' => $this->slug,
                'domain' => $this->domain,
                'status' => $this->status->value,
            ],
            'next' => 'verify_email',
            'email' => $this->contact_email,
        ];
    }
}
