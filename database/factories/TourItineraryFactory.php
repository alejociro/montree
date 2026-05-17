<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourItinerary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourItinerary>
 */
class TourItineraryFactory extends Factory
{
    protected $model = TourItinerary::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'step_number' => fake()->numberBetween(1, 10),
            'title' => fake()->sentence(3),
            'description' => fake()->paragraph(),
            'duration_label' => fake()->randomElement(['30 min', '1 h', '2 h', '4 h']),
        ];
    }
}
