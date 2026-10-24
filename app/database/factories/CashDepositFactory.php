<?php

namespace Database\Factories;

use App\Enums\CashDepositStatus;
use App\Enums\Role;
use App\Models\CashDeposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashDepositFactory extends Factory
{
    protected $model = CashDeposit::class;

    public function definition(): array
    {
        return [
            'sales_id' => User::factory()->state(['role' => Role::Sales]),
            'expected_amount' => 0,
            'claimed_amount' => 0,
            'status' => CashDepositStatus::Submitted,
        ];
    }
}
