<?php

namespace Database\Factories;

use App\Models\BlogCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogCategory>
 */
class BlogCategoryFactory extends Factory
{
    protected $model = BlogCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(1, 3), true);

        return [
            'parent_id'   => null,
            'name'        => ucwords($name),
            'slug'        => Str::slug($name) . '-' . fake()->unique()->numberBetween(100, 9999),
            'description' => fake()->sentence(),
            'is_active'   => true,
        ];
    }
}
