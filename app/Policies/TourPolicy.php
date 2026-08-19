<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tour;
use App\Models\User;

final class TourPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('tours.view');
    }

    public function view(User $user, Tour $tour): bool
    {
        return $user->can('tours.view');
    }

    public function create(User $user): bool
    {
        return $user->can('tours.create');
    }

    public function update(User $user, Tour $tour): bool
    {
        return $user->can('tours.update');
    }

    public function publish(User $user, Tour $tour): bool
    {
        return $user->can('tours.publish');
    }

    public function delete(User $user, Tour $tour): bool
    {
        return $user->can('tours.delete');
    }

    /**
     * WHY: archivar es la otra salida destructiva del catálogo y el catálogo de F018 no
     * tiene `tours.archive`; se apoya en `tours.delete`, que es el mismo conjunto de roles.
     */
    public function archive(User $user, Tour $tour): bool
    {
        return $user->can('tours.delete');
    }

    public function manageImages(User $user, Tour $tour): bool
    {
        return $user->can('tours.images.manage');
    }
}
