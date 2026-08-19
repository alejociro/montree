<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Promotion;
use App\Models\User;

final class PromotionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promotions.view');
    }

    public function view(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.view');
    }

    public function create(User $user): bool
    {
        return $user->can('promotions.create');
    }

    public function update(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.update');
    }

    public function delete(User $user, Promotion $promotion): bool
    {
        return $user->can('promotions.delete');
    }
}
