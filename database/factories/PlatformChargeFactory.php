<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommissionType;
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
            'commission_type' => CommissionType::Percentage,
            'applied_value' => 5,
            'amount' => round($base * 0.05, 2),
            'currency' => 'USD',
            'charged_at' => now(),
        ];
    }

    public function fixed(float $amount): self
    {
        return $this->state(fn () => [
            'commission_type' => CommissionType::Fixed,
            'applied_value' => $amount,
            'amount' => $amount,
        ]);
    }

    public function chargedAt(string $date): self
    {
        return $this->state(fn () => ['charged_at' => $date]);
    }
}
