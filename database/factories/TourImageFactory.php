<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Tour;
use App\Models\TourImage;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TourImage>
 */
class TourImageFactory extends Factory
{
    protected $model = TourImage::class;

    public function definition(): array
    {
        return [
            'tour_id' => Tour::factory(),
            'path' => 'tours/'.fake()->uuid().'.jpg',
            'alt_text' => fake()->sentence(4),
            'display_order' => fake()->numberBetween(0, 10),
            'is_cover' => false,
        ];
    }

    public function cover(): self
    {
        return $this->state(fn () => [
            'is_cover' => true,
            'display_order' => 0,
        ]);
    }
}
