<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\PromotionType;
use App\Models\Promotion;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Promotion>
 */
class PromotionFactory extends Factory
{
    protected $model = Promotion::class;

    public function definition(): array
    {
        return [
            'code' => Str::upper(Str::random(8)),
            'name' => fake()->words(3, true),
            'description' => fake()->sentence(),
            'type' => PromotionType::Percentage,
            'value' => fake()->numberBetween(5, 30),
            'min_amount' => null,
            'max_discount' => null,
            'max_uses' => fake()->numberBetween(10, 1000),
            'uses_count' => 0,
            'max_uses_per_user' => null,
            'starts_at' => now()->subDay(),
            'ends_at' => now()->addMonth(),
            'applicable_tours' => null,
            'is_active' => true,
        ];
    }

    public function fixed(): self
    {
        return $this->state(fn () => [
            'type' => PromotionType::Fixed,
            'value' => fake()->numberBetween(10, 100),
        ]);
    }

    public function expired(): self
    {
        return $this->state(fn () => [
            'starts_at' => now()->subMonths(2),
            'ends_at' => now()->subMonth(),
        ]);
    }

    public function exhausted(): self
    {
        return $this->state(fn () => [
            'max_uses' => 1,
            'uses_count' => 1,
        ]);
    }
}
