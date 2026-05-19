<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class AuthUserResource extends JsonResource
{
    public function __construct(User $user, private readonly ?Tenant $tenant)
    {
        parent::__construct($user);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isSuperAdmin = $this->resolveIsSuperAdmin();

        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'email_verified_at' => $this->email_verified_at?->toIso8601String(),
            'avatar_path' => $this->avatar_path,
            'avatar_url' => $this->resolveAvatarUrl(),
            'phone' => $this->phone,
            'tenantRole' => $this->resolveTenantRole($isSuperAdmin),
            'isSuperAdmin' => $isSuperAdmin,
        ];
    }

    private function resolveIsSuperAdmin(): bool
    {
        // WHY: super_admin role lives on sentinel team_id=0 (see RolesAndPermissionsSeeder).
        setPermissionsTeamId(0);
        $this->resource->unsetRelation('roles');

        return $this->resource->hasRole(UserRole::SuperAdmin->value);
    }

    private function resolveAvatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return asset('storage/'.ltrim($this->avatar_path, '/'));
    }

    private function resolveTenantRole(bool $isSuperAdmin): ?string
    {
        if ($this->tenant === null || $isSuperAdmin) {
            return null;
        }

        setPermissionsTeamId($this->tenant->id);
        $this->resource->unsetRelation('roles');

        $role = $this->resource->getRoleNames()->first();

        return is_string($role) ? $role : null;
    }
}
