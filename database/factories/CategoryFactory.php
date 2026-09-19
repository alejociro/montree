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
        // WHY: el sufijo aleatorio hace el nombre único, no `unique()`: con
        // `unique()->randomElement()` la factory se agotaba a la sexta categoría.
        $name = fake()->randomElement([
            'Senderismo', 'Aventura', 'Cultural', 'Gastronomía', 'Buceo', 'Avistamiento',
        ]).' '.Str::random(8);

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
