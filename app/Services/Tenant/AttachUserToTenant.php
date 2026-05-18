<?php

declare(strict_types=1);

namespace App\Services\Tenant;

use App\Enums\TenantMembershipStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;

final class AttachUserToTenant
{
    /**
     * Idempotently link a user to a tenant with a given role.
     *
     * Safe to call when the membership already exists: existing pivot rows are
     * preserved (status/joined_at are not overwritten). Roles are scoped per
     * tenant via spatie/permission team feature.
     *
     * The `$joinedVia` argument is accepted for forward-compatibility (origin
     * tracking) but is not persisted yet — the `tenant_user` pivot has no
     * `joined_via` column. See docs/specs/F001-auth/tasks.md notes.
     */
    public function handle(User $user, Tenant $tenant, UserRole $role, string $joinedVia): void
    {
        $tenant->users()->syncWithoutDetaching([
            $user->id => [
                'status' => TenantMembershipStatus::Active->value,
                'joined_at' => now(),
            ],
        ]);

        setPermissionsTeamId($tenant->id);
        $user->unsetRelation('roles');
        $user->syncRoles([$role->value]);
    }
}
