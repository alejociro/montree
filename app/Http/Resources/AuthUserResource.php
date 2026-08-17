<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Spatie\Permission\Models\Permission;

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
            'permissions' => $this->resolvePermissions($isSuperAdmin),
            'isSuperAdmin' => $isSuperAdmin,
            'mustSetPassword' => $this->resource->mustSetPassword(),
        ];
    }

    private function resolveIsSuperAdmin(): bool
    {
        return $this->resource->isSuperAdmin();
    }

    private function resolveAvatarUrl(): ?string
    {
        if ($this->avatar_path === null) {
            return null;
        }

        return asset('storage/'.ltrim($this->avatar_path, '/'));
    }

    /**
     * WHY: super_admin no tiene fila en ningún tenant (vive en el sentinel team 0) y su
     * bypass es `Gate::before`, no la tabla; se le entrega el catálogo completo para que
     * la UI use el mismo helper `can()` sin caso especial (contracts.md §2).
     *
     * @return array<int, string>
     */
    private function resolvePermissions(bool $isSuperAdmin): array
    {
        if ($isSuperAdmin) {
            return Permission::query()->orderBy('name')->pluck('name')->all();
        }

        if ($this->tenant === null) {
            return [];
        }

        $this->resource->loadRolesForTeam($this->tenant->id);

        return $this->resource->getAllPermissions()->pluck('name')->sort()->values()->all();
    }
}
