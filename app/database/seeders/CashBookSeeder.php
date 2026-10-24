<?php

namespace Database\Seeders;

use App\Enums\CashBookEntryType;
use App\Models\CashBookEntry;
use App\Models\ExpenseCategory;
use App\Models\User;
use App\Enums\Role;
use Illuminate\Database\Seeder;

class CashBookSeeder extends Seeder
{
    public function run(): void
    {
        $photos = UserSeeder::uploadSeedPhotos('proof', 5);
        $categories = ExpenseCategory::all();
        $admin = User::where('role', Role::Admin)->first();

        for ($i = 0; $i < 30; $i++) {
            CashBookEntry::create([
                'type' => CashBookEntryType::Pengeluaran,
                'amount' => fake()->numberBetween(50000, 2000000),
                'date' => now()->subDays(fake()->numberBetween(0, 180))->toDateString(),
                'catatan' => fake('id_ID')->sentence(4),
                'expense_category_id' => $categories->random()->id,
                'foto_path' => $i % 2 === 0 ? $photos[$i % count($photos)] : null,
                'user_id' => $admin?->id,
            ]);
        }
    }
}
