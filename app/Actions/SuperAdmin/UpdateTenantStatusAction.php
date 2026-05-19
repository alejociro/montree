<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantStatus;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantRestoredNotification;
use App\Notifications\SuperAdmin\TenantSuspendedNotification;
use Illuminate\Support\Facades\DB;
use RuntimeException;

final class UpdateTenantStatusAction
{
    public function handle(Tenant $tenant, TenantStatus $next, ?string $reason): Tenant
    {
        $previous = $tenant->status;

        if ($previous === $next) {
            throw new RuntimeException('Tenant already has the requested status.');
        }

        DB::transaction(function () use ($tenant, $next): void {
            $tenant->status = $next;
            $tenant->suspended_at = $next === TenantStatus::Suspended ? now() : null;
            $tenant->save();
        });

        $tenant->refresh();

        if ($next === TenantStatus::Suspended) {
            $this->notifyAdmins($tenant, new TenantSuspendedNotification(
                tenantName: $tenant->name,
                reason: $reason,
            ));
        }

        if ($next === TenantStatus::Active && $previous === TenantStatus::Suspended) {
            $this->notifyAdmins($tenant, new TenantRestoredNotification(
                tenantName: $tenant->name,
            ));
        }

        return $tenant;
    }

    private function notifyAdmins(Tenant $tenant, object $notification): void
    {
        setPermissionsTeamId($tenant->id);

        $admins = $tenant->users()
            ->wherePivot('status', TenantMembershipStatus::Active->value)
            ->get()
            ->filter(static function (User $user) {
                $user->unsetRelation('roles');

                return $user->hasRole(UserRole::Admin->value);
            });

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
