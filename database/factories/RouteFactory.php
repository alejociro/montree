<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RouteKind;
use App\Enums\RouteSeason;
use App\Enums\TourDifficulty;
use App\Models\Route;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Route>
 */
class RouteFactory extends Factory
{
    protected $model = Route::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Ruta '.fake()->unique()->streetName(),
            'description' => fake()->optional()->sentence(),
            'kind' => fake()->randomElement(RouteKind::cases()),
            'difficulty' => fake()->randomElement(TourDifficulty::cases()),
            'start_point' => fake()->optional()->address(),
            'city' => fake()->city(),
            'state' => fake()->optional()->state(),
            'country' => 'Colombia',
            'distance_km' => fake()->optional()->randomFloat(2, 1, 300),
            'duration_hours' => fake()->optional()->randomFloat(1, 0.5, 48),
            'group_capacity' => fake()->numberBetween(6, 40),
            'seasons' => [RouteSeason::AllYear->value],
        ];
    }
}
