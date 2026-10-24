<?php

namespace Database\Seeders;

use App\Models\ExpenseCategory;
use Illuminate\Database\Seeder;

class ExpenseCategorySeeder extends Seeder
{
    public function run(): void
    {
        collect(['Operasional', 'Gaji', 'Transport', 'Listrik & Air', 'Sewa', 'Lain-lain'])
            ->each(fn (string $name) => ExpenseCategory::create(['name' => $name, 'is_active' => true]));
    }
}
