<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TourStopKind;
use App\Models\Tour;
use App\Models\TourStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourStop>
 */
class TourStopFactory extends Factory
{
    protected $model = TourStop::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'position' => fake()->numberBetween(1, 20),
            'kind' => TourStopKind::Site,
            'code' => (string) fake()->numberBetween(1, 9),
            'label' => null,
            'name' => fake()->city(),
            'place' => fake()->state(),
            'time_label' => fake()->randomElement(['8:00 a. m.', '10:30 a. m.', '2:00 p. m.']),
            'latitude' => fake()->latitude(3, 6),
            'longitude' => fake()->longitude(-76, -74),
            'itinerary_step' => null,
        ];
    }

    public function pickup(): self
    {
        return $this->state([
            'kind' => TourStopKind::Pickup,
            'code' => 'A',
            'label' => 'Recogida',
            'position' => 1,
        ]);
    }

    public function drop(): self
    {
        return $this->state([
            'kind' => TourStopKind::Drop,
            'code' => 'B',
            'label' => 'Regreso',
            'position' => 99,
        ]);
    }
}
