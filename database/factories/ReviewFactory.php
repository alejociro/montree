<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ReviewStatus;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Review>
 */
class ReviewFactory extends Factory
{
    protected $model = Review::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'user_id' => User::factory(),
            'booking_id' => Booking::factory(),
            'rating' => fake()->numberBetween(1, 5),
            'title' => fake()->sentence(5),
            'comment' => fake()->paragraph(),
            'status' => ReviewStatus::Pending,
            'admin_response' => null,
            'responded_by' => null,
            'responded_at' => null,
            'approved_at' => null,
            'rejection_reason' => null,
        ];
    }

    public function approved(): self
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::Approved,
            'approved_at' => now(),
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn () => [
            'status' => ReviewStatus::Rejected,
            'rejection_reason' => 'Inappropriate content',
        ]);
    }
}
