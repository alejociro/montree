<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TourStopKind;
use App\Models\Route;
use App\Models\RouteStop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<RouteStop>
 */
class RouteStopFactory extends Factory
{
    protected $model = RouteStop::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'route_id' => Route::factory(),
            'position' => 1,
            'name' => fake()->streetName(),
            'kind' => TourStopKind::Site,
            'time_label' => '8:00 a. m.',
        ];
    }
}
