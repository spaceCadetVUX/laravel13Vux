<?php

namespace Database\Factories;

use App\Models\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Category>
 */
class CategoryFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(1, 3), true);

        return [
            'parent_id'   => null,
            'name'        => ucwords($name),
            'slug'        => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->optional()->sentence(),
            'image_path'  => null,
            'sort_order'  => 0,
            'is_active'   => true,
        ];
    }

    /** State: nested child of a given parent. */
    public function child(Category $parent): static
    {
        return $this->state(fn (array $attributes) => [
            'parent_id' => $parent->id,
        ]);
    }

    /** State: inactive. */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
