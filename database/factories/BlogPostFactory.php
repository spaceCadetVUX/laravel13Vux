<?php

namespace Database\Factories;

use App\Enums\BlogPostStatus;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<BlogPost>
 */
class BlogPostFactory extends Factory
{
    protected $model = BlogPost::class;

    public function definition(): array
    {
        $title = fake()->unique()->sentence(fake()->numberBetween(4, 8));

        return [
            'author_id'        => User::factory(),
            'blog_category_id' => BlogCategory::factory(),
            'title'            => $title,
            'slug'             => Str::slug($title) . '-' . fake()->unique()->numberBetween(1000, 99999),
            'excerpt'          => fake()->paragraph(),
            'content'          => fake()->paragraphs(5, true),
            'featured_image'   => null,
            'status'           => BlogPostStatus::Published,
            'published_at'     => now()->subDays(fake()->numberBetween(1, 30)),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => BlogPostStatus::Published,
            'published_at' => now()->subDays(fake()->numberBetween(1, 30)),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes) => [
            'status'       => BlogPostStatus::Draft,
            'published_at' => null,
        ]);
    }
}
