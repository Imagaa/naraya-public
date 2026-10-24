<?php

namespace Database\Factories;

use App\Enums\StockMovementType;
use App\Enums\Role;
use App\Models\Product;
use App\Models\StockMovement;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class StockMovementFactory extends Factory
{
    protected $model = StockMovement::class;

    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'type' => StockMovementType::In,
            'quantity' => fake()->numberBetween(10, 100),
            'reference' => null,
            'note' => null,
            'user_id' => User::factory()->state(['role' => Role::Admin]),
        ];
    }
}
