<?php

namespace Database\Factories;

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Models\Complaint;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

class ComplaintFactory extends Factory
{
    protected $model = Complaint::class;

    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'tipe' => ComplaintType::KomplainPascaSelesai,
            'alasan' => fake()->sentence(),
            'status' => ComplaintStatus::Diajukan,
        ];
    }
}
