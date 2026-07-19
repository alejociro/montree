<?php

declare(strict_types=1);

namespace Database\Factories;

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
            'address' => fake()->optional()->address(),
            'contact_phone' => fake()->numerify('+57 3## ### ####'),
            'contact_email' => fake()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
