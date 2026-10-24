<?php

namespace Database\Factories;

use App\Models\SalesProfile;
use Illuminate\Database\Eloquent\Factories\Factory;

class SalesProfileFactory extends Factory
{
    protected $model = SalesProfile::class;

    public function definition(): array
    {
        return [
            'phone' => fake()->phoneNumber(),
            'joined_at' => now(),
            'monthly_target' => fake()->numberBetween(20000000, 60000000),
            'is_active' => true,
        ];
    }
}
