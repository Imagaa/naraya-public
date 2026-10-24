<?php

namespace Database\Seeders;

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\Redemption;
use App\Models\Reward;
use App\Models\User;
use Illuminate\Database\Seeder;

class RedemptionSeeder extends Seeder
{
    public function run(): void
    {
        $photos = UserSeeder::uploadSeedPhotos('proof', 5);
        $buyers = UserSeeder::shoppingBuyers()->get();
        $rewards = Reward::all();
        $admin = User::where('role', Role::Admin)->first();
        $statuses = RedemptionStatus::cases();

        for ($i = 0; $i < 40; $i++) {
            $status = $statuses[$i % count($statuses)];

            Redemption::create([
                'user_id' => $buyers->random()->id,
                'reward_id' => $rewards->random()->id,
                'point_cost' => fake()->numberBetween(20, 100),
                'status' => $status,
                'alasan_penolakan' => $status === RedemptionStatus::Ditolak ? 'Stok hadiah habis' : null,
                'bukti_path' => $status === RedemptionStatus::Diserahkan ? $photos[$i % count($photos)] : null,
                'diproses_oleh' => in_array($status, [RedemptionStatus::Disetujui, RedemptionStatus::Ditolak, RedemptionStatus::Diserahkan], true) ? $admin?->id : null,
                'diserahkan_oleh' => $status === RedemptionStatus::Diserahkan ? $admin?->id : null,
                'diserahkan_at' => $status === RedemptionStatus::Diserahkan ? now()->subDays(fake()->numberBetween(1, 30)) : null,
            ]);
        }
    }
}
