<?php

declare(strict_types=1);

namespace App\Http\Resources\Team;

use App\Http\Resources\Role\RoleSummaryResource;
use App\Models\TenantUser;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
final class TeamMemberResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var User $member */
        $member = $this->resource;

        /** @var TenantUser $membership */
        $membership = $member->pivot;

        return [
            'id' => $member->getKey(),
            'name' => $member->name,
            'email' => $member->email,
            'roles' => RoleSummaryResource::collection($member->roles)->resolve(),
            'status' => $membership->status->value,
            'invited_at' => $membership->invited_at?->toIso8601String(),
            'joined_at' => $membership->joined_at?->toIso8601String(),
            'last_login_at' => $member->last_login_at?->toIso8601String(),
        ];
    }
}
