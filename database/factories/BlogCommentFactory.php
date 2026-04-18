<?php

namespace Database\Factories;

use App\Models\BlogComment;
use App\Models\BlogPost;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BlogComment>
 */
class BlogCommentFactory extends Factory
{
    protected $model = BlogComment::class;

    public function definition(): array
    {
        return [
            'blog_post_id' => BlogPost::factory(),
            'user_id'      => User::factory(),
            'body'         => fake()->paragraph(),
            'is_approved'  => false,
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => true,
        ]);
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_approved' => false,
        ]);
    }
}
