<?php

namespace Database\Factories;

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RedemptionFactory extends Factory
{
    protected $model = Redemption::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => Role::User]),
            'reward_id' => Reward::factory(),
            'point_cost' => 20,
            'status' => RedemptionStatus::Diajukan,
        ];
    }
}
