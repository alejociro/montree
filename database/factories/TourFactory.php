<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\TourDifficulty;
use App\Enums\TourStatus;
use App\Models\Tour;
use App\Models\TourDate;
use App\Models\TourImage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Tour>
 */
class TourFactory extends Factory
{
    protected $model = Tour::class;

    public function definition(): array
    {
        $name = fake()->unique()->sentence(3);

        return [
            'category_id' => null,
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(4)),
            'short_description' => fake()->sentence(15),
            'description' => fake()->paragraphs(3, true),
            'duration_hours' => fake()->numberBetween(2, 72),
            'difficulty' => fake()->randomElement(TourDifficulty::cases()),
            'base_price' => fake()->randomFloat(2, 50, 1500),
            'currency' => 'USD',
            'default_capacity' => fake()->numberBetween(4, 20),
            'meeting_point' => fake()->address(),
            'meeting_latitude' => fake()->latitude(),
            'meeting_longitude' => fake()->longitude(),
            'includes' => ['Guía', 'Transporte', 'Almuerzo'],
            'excludes' => ['Propinas', 'Bebidas alcohólicas'],
            'requirements' => ['Mayores de 12 años', 'Buena condición física'],
            'status' => TourStatus::Draft,
            'rating_average' => 0,
            'rating_count' => 0,
        ];
    }

    public function active(): self
    {
        return $this->state(fn () => ['status' => TourStatus::Active]);
    }

    public function archived(): self
    {
        return $this->state(fn () => ['status' => TourStatus::Archived]);
    }

    public function withCover(): self
    {
        return $this->afterCreating(function (Tour $tour) {
            TourImage::factory()->cover()->for($tour)->create();
        });
    }

    public function withFutureDate(int $capacity = 10): self
    {
        return $this->afterCreating(function (Tour $tour) use ($capacity) {
            TourDate::factory()->for($tour)->state([
                'capacity' => $capacity,
                'starts_at' => now()->addDays(7),
            ])->create();
        });
    }
}
