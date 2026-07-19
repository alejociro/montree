<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Provider;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Provider>
 */
class ProviderFactory extends Factory
{
    protected $model = Provider::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->company(),
            'service_type' => fake()->randomElement(['transporte', 'alimentación', 'equipo', 'guianza']),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->numerify('+57 3## ### ####'),
            'contact_email' => fake()->safeEmail(),
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
