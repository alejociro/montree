<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Booking;
use App\Models\PlatformCharge;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PlatformCharge>
 */
class PlatformChargeFactory extends Factory
{
    protected $model = PlatformCharge::class;

    public function definition(): array
    {
        $base = fake()->randomFloat(2, 100, 2000);

        return [
            'tenant_id' => Tenant::factory(),
            'booking_id' => Booking::factory(),
            'payment_id' => null,
            'base_amount' => $base,
            'applied_rate' => 5,
            'amount' => round($base * 0.05, 2),
            'currency' => 'USD',
            'tier_from' => '0.00',
            'tier_to' => null,
            'max_charge' => null,
            'was_capped' => false,
            'schedule_scope' => 'global',
            'charged_at' => now(),
        ];
    }

    public function chargedAt(string $date): self
    {
        return $this->state(fn () => ['charged_at' => $date]);
    }
}
