<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\CommissionSchedule;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CommissionSchedule>
 */
class CommissionScheduleFactory extends Factory
{
    protected $model = CommissionSchedule::class;

    public function definition(): array
    {
        return [
            'tenant_id' => Tenant::factory(),
            'currency' => 'COP',
            'tiers' => [
                ['from' => '0.00', 'to' => null, 'rate' => '5.00'],
            ],
            'max_charge' => null,
        ];
    }

    public function global(): self
    {
        return $this->state(fn () => ['tenant_id' => null]);
    }

    /**
     * @param  array<int, array{from: string, to: string|null, rate: string}>  $tiers
     */
    public function withTiers(array $tiers): self
    {
        return $this->state(fn () => ['tiers' => $tiers]);
    }

    public function withMaxCharge(?string $maxCharge): self
    {
        return $this->state(fn () => ['max_charge' => $maxCharge]);
    }
}
