<?php

declare(strict_types=1);

namespace App\Actions\Favorite;

use App\Models\Favorite;
use App\Models\Tour;
use App\Models\User;

final class ToggleFavoriteAction
{
    public function handle(User $user, Tour $tour): bool
    {
        $existing = Favorite::query()
            ->where('user_id', $user->id)
            ->where('tour_id', $tour->id)
            ->first();

        if ($existing !== null) {
            $existing->delete();

            return false;
        }

        Favorite::query()->create([
            'user_id' => $user->id,
            'tour_id' => $tour->id,
        ]);

        return true;
    }
}
