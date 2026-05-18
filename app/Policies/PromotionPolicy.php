<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Promotion;
use App\Models\User;

final class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function create(User $user): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $this->isAdminOrOperator($user);
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $this->isAdmin($user);
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
