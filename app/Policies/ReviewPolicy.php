<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Review;
use App\Models\User;

final class ReviewPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('reviews.view');
    }

    public function moderate(User $user, Review $review): bool
    {
        return $user->can('reviews.moderate');
    }

    public function respond(User $user, Review $review): bool
    {
        return $user->can('reviews.respond');
    }
}
