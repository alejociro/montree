<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Hotel;
use App\Models\HotelRoom;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HotelRoom>
 */
class HotelRoomFactory extends Factory
{
    protected $model = HotelRoom::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'hotel_id' => Hotel::factory(),
            'position' => 1,
            'name' => 'Doble',
            'quantity' => fake()->numberBetween(1, 12),
            'nightly_rate' => fake()->randomFloat(2, 20, 200),
        ];
    }
}
