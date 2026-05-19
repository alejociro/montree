<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Exceptions\ReviewException;
use App\Models\Review;
use App\Models\User;

final class RespondReviewAction
{
    public function handle(Review $review, User $admin, string $response): Review
    {
        if ($review->admin_response !== null) {
            throw ReviewException::alreadyResponded();
        }

        $review->update([
            'admin_response' => $response,
            'responded_by' => $admin->id,
            'responded_at' => now(),
        ]);

        return $review->fresh();
    }
}
