<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

class ProductFactory extends Factory
{
    protected $model = Product::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->numerify('89############'),
            'category_id' => Category::factory(),
            'price' => fake()->numberBetween(2000, 50000),
            'stock' => fake()->numberBetween(0, 100),
            'is_active' => true,
        ];
    }
}
