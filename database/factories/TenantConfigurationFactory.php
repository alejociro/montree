<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PlaceToPayEnvironment;
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
            // T12: sin regla por defecto en la factory; los tests que la
            // necesiten la fijan explícitamente.
            'booking_advance_hours' => null,
            'booking_expiration_minutes' => 30,
            'min_partial_payment_pct' => 30,
            // T14: sin comercio propio, el ambiente no se usa, pero se fija
            // igual que el default de la columna para que un `create()` sin
            // este campo devuelva el mismo modelo que quedaría en la BD.
            'placetopay_environment' => PlaceToPayEnvironment::Test,
        ];
    }
}
