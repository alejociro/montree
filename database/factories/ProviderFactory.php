<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PaymentTerms;
use App\Enums\ProviderServiceType;
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
            'service_type' => fake()->randomElement(ProviderServiceType::cases()),
            'legal_name' => fake()->company().' S.A.S.',
            'tax_id' => fake()->numerify('9##.###.###-#'),
            'payment_terms' => fake()->randomElement(PaymentTerms::cases()),
            'contact_name' => fake()->name(),
            'contact_phone' => fake()->numerify('+57 3## ### ####'),
            'contact_email' => fake()->safeEmail(),
            'city' => fake()->city(),
            'currency' => 'USD',
            'notes' => fake()->optional()->sentence(),
        ];
    }
}
