<?php

namespace Database\Factories;

use App\Models\Reward;
use Illuminate\Database\Eloquent\Factories\Factory;

class RewardFactory extends Factory
{
    protected $model = Reward::class;

    public function definition(): array
    {
        return [
            'name' => fake()->words(2, true),
            'point_cost' => fake()->numberBetween(10, 100),
            'stock' => fake()->numberBetween(1, 50),
            'is_active' => true,
        ];
    }
}
