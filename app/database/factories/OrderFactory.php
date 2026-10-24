<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class OrderFactory extends Factory
{
    protected $model = Order::class;

    public function definition(): array
    {
        return [
            'buyer_id' => User::factory()->state(['role' => Role::User]),
            'status' => OrderStatus::New,
            'payment_method' => 'cash',
            'payment_status' => PaymentStatus::Paid,
            'subtotal' => 0,
            'total' => 0,
            'delivery_address' => fake()->address(),
        ];
    }
}
