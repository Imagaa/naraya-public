<?php

namespace Database\Factories;

use App\Enums\CashCollectionStatus;
use App\Enums\Role;
use App\Models\CashCollection;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashCollectionFactory extends Factory
{
    protected $model = CashCollection::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'sales_id' => User::factory()->state(['role' => Role::Sales]),
            'amount' => fake()->numberBetween(10000, 500000),
            'status' => CashCollectionStatus::Held,
            'collected_at' => now(),
        ];
    }
}
