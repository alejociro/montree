<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;

final class DashboardPolicy
{
    public function view(User $user): bool
    {
        return $user->can('dashboard.view');
    }

    public function viewReports(User $user): bool
    {
        return $user->can('reports.view');
    }

    public function exportReports(User $user): bool
    {
        return $user->can('reports.export');
    }
}
