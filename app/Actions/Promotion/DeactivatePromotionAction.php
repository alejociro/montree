<?php

declare(strict_types=1);

namespace App\Actions\Promotion;

use App\Models\Promotion;

final class DeactivatePromotionAction
{
    /**
     * Soft "deactivate": flips `is_active` to false if the promotion was ever used,
     * otherwise hard-deletes it.
     */
    public function handle(Promotion $promotion): void
    {
        if ($promotion->uses_count > 0) {
            $promotion->is_active = false;
            $promotion->save();

            return;
        }

        $promotion->delete();
    }
}
