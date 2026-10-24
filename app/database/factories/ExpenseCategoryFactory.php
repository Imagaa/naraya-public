<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

class ExpenseCategoryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Operasional', 'Gaji', 'Listrik', 'ATK', 'Transport']),
            'is_active' => true,
        ];
    }
}
