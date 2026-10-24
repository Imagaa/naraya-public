<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\User;
use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;

class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'sales_id' => User::factory()->state(['role' => Role::Sales]),
            'buyer_id' => User::factory()->state(['role' => Role::User]),
            'notes' => null,
            'photo_path' => null,
            'visited_at' => now(),
        ];
    }
}
