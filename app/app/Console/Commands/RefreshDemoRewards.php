<?php

namespace App\Console\Commands;

use App\Models\Reward;
use Database\Seeders\RewardSeeder;
use Illuminate\Console\Command;

/**
 * Brings an already-seeded database's rewards up to RewardSeeder::REWARDS
 * without a full reseed: existing rows (in id order) are renamed and
 * re-photographed in place, so redemptions keep pointing at the same rows;
 * missing rows are created and extra rows are left alone.
 */
class RefreshDemoRewards extends Command
{
    protected $signature = 'demo:refresh-rewards';

    protected $description = 'Update demo rewards in place to the real names and unique photos';

    public function handle(): int
    {
        $paths = RewardSeeder::uploadPhotos();
        $existing = Reward::orderBy('id')->get()->values();

        foreach (RewardSeeder::REWARDS as $i => [$name, $cost, $stock]) {
            $data = ['name' => $name, 'point_cost' => $cost, 'stock' => $stock, 'photo_path' => $paths[$i], 'is_active' => true];
            isset($existing[$i]) ? $existing[$i]->update($data) : Reward::create($data);
        }

        $this->info('Rewards refreshed: '.count(RewardSeeder::REWARDS));

        return self::SUCCESS;
    }
}
