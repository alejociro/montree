<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tenant;
use App\Models\TenantConfiguration;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TenantConfiguration>
 */
class TenantConfigurationFactory extends Factory
{
    protected $model = TenantConfiguration::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'primary_color' => fake()->hexColor(),
            'secondary_color' => fake()->hexColor(),
            'logo_path' => null,
            'favicon_path' => null,
            'hero_image_path' => null,
            'currency' => 'USD',
            'timezone' => 'America/Bogota',
            'locale' => 'es',
            'tagline' => fake()->catchPhrase(),
            'description' => fake()->sentence(12),
            'social_links' => [
                'instagram' => 'https://instagram.com/'.fake()->userName(),
            ],
            'contact_info' => [
                'email' => fake()->companyEmail(),
                'phone' => fake()->phoneNumber(),
            ],
            'custom_css' => null,
            'reviews_require_moderation' => true,
            'require_traveler_details' => true,
            'booking_advance_hours' => 24,
            'booking_expiration_minutes' => 30,
            'min_partial_payment_pct' => 30,
        ];
    }
}
