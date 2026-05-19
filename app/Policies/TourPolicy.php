<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Tour;
use App\Models\User;

final class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isStaff($user);
    }

    public function view(User $user, Tour $tour): bool
    {
        return $this->isStaff($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function update(User $user, Tour $tour): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function delete(User $user, Tour $tour): bool
    {
        return $this->isAdmin($user);
    }

    public function archive(User $user, Tour $tour): bool
    {
        return $this->isAdmin($user);
    }

    private function isStaff(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Operator->value,
            UserRole::Guide->value,
        ]);
    }

    private function isAdminOrOperator(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
            UserRole::Operator->value,
        ]);
    }

    private function isAdmin(User $user): bool
    {
        return $user->hasAnyRole([
            UserRole::SuperAdmin->value,
            UserRole::Admin->value,
        ]);
    }
}
