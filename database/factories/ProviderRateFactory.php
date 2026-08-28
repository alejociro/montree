<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\RateUnit;
use App\Models\Provider;
use App\Models\ProviderRate;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderRate>
 */
class ProviderRateFactory extends Factory
{
    protected $model = ProviderRate::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'position' => 1,
            'concept' => 'Bus 40 puestos',
            'amount' => fake()->randomFloat(2, 50, 900),
            'unit' => RateUnit::PerService,
        ];
    }
}
