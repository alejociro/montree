<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement([
            'Senderismo', 'Aventura', 'Cultural', 'Gastronomía', 'Buceo', 'Avistamiento',
        ]).' '.Str::random(4);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'icon' => fake()->randomElement(['mountain', 'compass', 'palette', 'utensils', 'waves', 'binoculars']),
            'description' => fake()->sentence(),
            'display_order' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }

    public function inactive(): self
    {
        return $this->state(fn () => ['is_active' => false]);
    }
}
