<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TourDateStatus;
use App\Models\Tour;
use App\Models\TourDate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourDate>
 */
class TourDateFactory extends Factory
{
    protected $model = TourDate::class;

    public function definition(): array
    {
        $startsAt = fake()->dateTimeBetween('+1 day', '+90 days');

        return [
            'tour_id' => Tour::factory(),
            'guide_id' => null,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->modify('+4 hours'),
            'capacity' => fake()->numberBetween(4, 20),
            'booked_count' => 0,
            'price_override' => null,
            'status' => TourDateStatus::Open,
            'notes' => null,
        ];
    }

    public function full(): self
    {
        return $this->state(fn (array $attrs) => [
            'booked_count' => $attrs['capacity'] ?? 10,
            'status' => TourDateStatus::Full,
        ]);
    }

    public function past(): self
    {
        return $this->state(fn () => [
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->subDays(7)->addHours(4),
        ]);
    }
}
