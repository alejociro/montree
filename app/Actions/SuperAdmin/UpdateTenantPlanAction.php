<?php

declare(strict_types=1);

namespace App\Actions\SuperAdmin;

use App\Enums\TenantMembershipStatus;
use App\Enums\TenantPlan;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Notifications\SuperAdmin\TenantPlanChangedNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

final class UpdateTenantPlanAction
{
    public function handle(Tenant $tenant, TenantPlan $next): Tenant
    {
        $previous = $tenant->plan;

        DB::transaction(function () use ($tenant, $next): void {
            $tenant->plan = $next;
            $tenant->save();
        });

        $tenant->refresh();

        $this->warnOnDowngradeOverflow($tenant, $previous, $next);
        $this->notifyAdmins($tenant, $previous, $next);

        return $tenant;
    }

    private function warnOnDowngradeOverflow(Tenant $tenant, TenantPlan $previous, TenantPlan $next): void
    {
        if ($previous === $next) {
            return;
        }

        $newLimits = $next->limits();
        $maxTours = (int) ($newLimits['max_tours'] ?? 0);
        $maxStaff = (int) ($newLimits['max_staff'] ?? 0);

        $currentTours = $tenant->tours()->count();
        $currentStaff = $tenant->users()->count();

        if ($currentTours > $maxTours || $currentStaff > $maxStaff) {
            Log::warning('Tenant plan downgrade exceeds new limits (soft limit applied).', [
                'tenant_id' => $tenant->id,
                'previous_plan' => $previous->value,
                'new_plan' => $next->value,
                'current_tours' => $currentTours,
                'max_tours' => $maxTours,
                'current_staff' => $currentStaff,
                'max_staff' => $maxStaff,
            ]);
        }
    }

    private function notifyAdmins(Tenant $tenant, TenantPlan $previous, TenantPlan $next): void
    {
        setPermissionsTeamId($tenant->id);

        $admins = $tenant->users()
            ->wherePivot('status', TenantMembershipStatus::Active->value)
            ->get()
            ->filter(static function (User $user) {
                $user->unsetRelation('roles');

                return $user->hasRole(UserRole::Admin->value);
            });

        $notification = new TenantPlanChangedNotification(
            tenantName: $tenant->name,
            previousPlan: $previous->value,
            newPlan: $next->value,
        );

        foreach ($admins as $admin) {
            $admin->notify($notification);
        }
    }
}
