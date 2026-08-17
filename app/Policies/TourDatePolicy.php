<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TourDate;
use App\Models\User;

final class TourDatePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('departures.view');
    }

    public function view(User $user, TourDate $tourDate): bool
    {
        return $user->can('departures.view');
    }

    public function create(User $user): bool
    {
        return $user->can('departures.create');
    }

    public function update(User $user, TourDate $tourDate): bool
    {
        return $user->can('departures.update');
    }

    public function cancel(User $user, TourDate $tourDate): bool
    {
        return $user->can('departures.cancel');
    }

    public function delete(User $user, TourDate $tourDate): bool
    {
        return $user->can('departures.delete');
    }

    public function assignGuide(User $user, TourDate $tourDate): bool
    {
        return $user->can('departures.assign_guide');
    }
}
