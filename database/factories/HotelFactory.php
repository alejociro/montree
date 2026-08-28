<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\AccommodationType;
use App\Enums\CancellationPolicy;
use App\Enums\MealPlan;
use App\Models\Hotel;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Hotel>
 */
class HotelFactory extends Factory
{
    protected $model = Hotel::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Hotel '.fake()->unique()->lastName(),
            'accommodation_type' => fake()->randomElement(AccommodationType::cases()),
            'star_rating' => fake()->optional()->numberBetween(2, 5),
            'address' => fake()->optional()->address(),
            'city' => fake()->city(),
            'country' => 'Colombia',
            'total_capacity' => fake()->numberBetween(10, 90),
            'currency' => 'USD',
            'check_in' => '3:00 p. m.',
            'check_out' => '11:00 a. m.',
            'meal_plan' => fake()->randomElement(MealPlan::cases()),
            'cancellation_policy' => fake()->randomElement(CancellationPolicy::cases()),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->numerify('+57 3## ### ####'),
            'contact_email' => fake()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
