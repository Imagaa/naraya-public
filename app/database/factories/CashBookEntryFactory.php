<?php

namespace Database\Factories;

use App\Enums\CashBookEntryType;
use Illuminate\Database\Eloquent\Factories\Factory;

class CashBookEntryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'type' => CashBookEntryType::Pemasukan,
            'amount' => fake()->numberBetween(10000, 500000),
            'date' => now()->toDateString(),
            'catatan' => null,
            'expense_category_id' => null,
            'foto_path' => null,
            'reference' => null,
            'user_id' => null,
        ];
    }
}
