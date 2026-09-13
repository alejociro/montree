<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\ProviderDocumentType;
use App\Models\Provider;
use App\Models\ProviderDocument;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProviderDocument>
 */
class ProviderDocumentFactory extends Factory
{
    protected $model = ProviderDocument::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'provider_id' => Provider::factory(),
            'position' => 1,
            'kind' => ProviderDocumentType::LiabilityPolicy,
            'number' => fake()->numerify('POL-######'),
            'expires_at' => fake()->dateTimeBetween('+1 month', '+2 years'),
        ];
    }
}
