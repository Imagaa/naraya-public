<?php

namespace Database\Seeders;

use App\Notifications\AdminNotice;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo data is for local and the dev server only; production starts empty (2026-10-11).
        if (app()->isProduction()) {
            throw new \RuntimeException('Seeder demo tidak boleh dijalankan di production.');
        }

        // The seeders fire the real observers; keep demo data out of the admin bell.
        AdminNotice::$muted = true;

        try {
            $this->call([
                AreaSeeder::class,
                UserSeeder::class,
                CategorySeeder::class,
                ProductSeeder::class,
                RewardSeeder::class,
                OrderSeeder::class,
                DeliveryFlowSeeder::class,
                ComplaintSeeder::class,
                CashSeeder::class,
                RedemptionSeeder::class,
                AttendanceSeeder::class,
                ExpenseCategorySeeder::class,
                CashBookSeeder::class,
                StockAlertSeeder::class,
                NotificationSeeder::class,
                ToySeeder::class, // last: the order/notification seeders above pick any product, and orders are Naraya's
            ]);
        } finally {
            AdminNotice::$muted = false;
        }
    }
}
