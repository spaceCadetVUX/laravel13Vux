<?php

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->words(fake()->numberBetween(2, 4), true);

        return [
            'category_id'       => null,
            'name'              => ucwords($name),
            'slug'              => Str::slug($name) . '-' . fake()->unique()->numberBetween(1000, 99999),
            'sku'               => strtoupper(Str::random(3) . '-' . fake()->unique()->numerify('####')),
            'short_description' => fake()->sentence(),
            'description'       => fake()->paragraphs(3, true),
            'price'             => fake()->randomFloat(2, 5, 2000),
            'sale_price'        => null,
            'stock_quantity'    => fake()->numberBetween(0, 200),
            'is_active'         => true,
        ];
    }

    /** State: on sale at a discounted price. */
    public function onSale(): static
    {
        return $this->state(function (array $attributes) {
            return [
                'sale_price' => round((float) $attributes['price'] * fake()->randomFloat(2, 0.5, 0.9), 2),
            ];
        });
    }

    /** State: out of stock. */
    public function outOfStock(): static
    {
        return $this->state(fn (array $attributes) => [
            'stock_quantity' => 0,
        ]);
    }

    /** State: inactive (not visible in storefront). */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_active' => false,
        ]);
    }
}
