<?php

namespace Database\Seeders;

use App\Models\Reward;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Ten rewards with real names and one photo each (database/seeders/data/rewards,
 * credited in CREDITS.txt). Costs climb so a typical buyer can afford a few and
 * aims for the rest; the most expensive one is out of stock (edge case).
 */
class RewardSeeder extends Seeder
{
    /** [name, point cost, stock, photo file] — also used by `demo:refresh-rewards`. */
    public const REWARDS = [
        ['Mug Naraya', 25, 30, '1.jpg'],
        ['Kaos Naraya', 40, 25, '2.jpg'],
        ['Topi Naraya', 55, 20, '3.jpg'],
        ['Tas Belanja Naraya', 70, 18, '4.jpg'],
        ['Kalender Meja', 85, 15, '5.jpg'],
        ['Tumbler Naraya', 110, 12, '6.jpg'],
        ['Payung Naraya', 140, 10, '7.jpg'],
        ['Kalkulator', 170, 8, '8.jpg'],
        ['Kipas Angin Meja', 220, 5, '9.jpg'],
        ['Timbangan Digital', 300, 0, '10.jpg'],
    ];

    public function run(): void
    {
        foreach (self::uploadPhotos() as $i => $path) {
            [$name, $cost, $stock] = self::REWARDS[$i];
            Reward::create(['name' => $name, 'point_cost' => $cost, 'stock' => $stock, 'photo_path' => $path, 'is_active' => true]);
        }
    }

    /** @return array<int, string> stored path per entry of REWARDS, same order */
    public static function uploadPhotos(): array
    {
        return array_map(function (array $reward) {
            // Named after the reward, not "1.jpg": the media host sits behind a CDN that keeps
            // serving whatever an old URL used to hold, so a new photo needs a new URL.
            $remote = 'seed/rewards/'.\Illuminate\Support\Str::slug($reward[0]).'.jpg';
            Storage::disk('s3')->put($remote, file_get_contents(database_path('seeders/data/rewards/'.$reward[3])));

            return $remote;
        }, self::REWARDS);
    }
}
