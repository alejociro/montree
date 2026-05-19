<?php

declare(strict_types=1);

namespace App\Actions\Review;

use App\Enums\BookingStatus;
use App\Enums\ReviewStatus;
use App\Exceptions\ReviewException;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tenant;
use App\Models\User;

final class CreateReviewAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(User $user, Booking $booking, array $data): Review
    {
        if ($booking->status !== BookingStatus::Completed) {
            throw ReviewException::bookingNotCompleted();
        }

        if ($booking->user_id !== $user->id) {
            throw ReviewException::bookingNotCompleted();
        }

        if (Review::query()->where('booking_id', $booking->id)->exists()) {
            throw ReviewException::alreadyReviewed();
        }

        $tenant = Tenant::current();
        $moderationOn = (bool) ($tenant?->configuration?->reviews_require_moderation ?? true);

        return Review::query()->create([
            'tour_id' => $booking->tour_id,
            'user_id' => $user->id,
            'booking_id' => $booking->id,
            'rating' => (int) $data['rating'],
            'title' => $data['title'] ?? null,
            'comment' => $data['comment'] ?? null,
            'status' => $moderationOn ? ReviewStatus::Pending : ReviewStatus::Approved,
            'approved_at' => $moderationOn ? null : now(),
        ]);
    }
}
