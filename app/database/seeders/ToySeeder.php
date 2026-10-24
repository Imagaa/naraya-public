<?php

namespace Database\Seeders;

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The Naraya Toys demo catalogue: everyday warung toys with free-licensed
 * photos from Wikimedia Commons, committed under database/seeders/data/toys*
 * by `php artisan demo:fetch-toys`. Prices are invented but deterministic per
 * product. Runs once: when the Toys line already has products it does nothing,
 * so it is safe to run on production (`db:seed --class=ToySeeder`).
 */
class ToySeeder extends Seeder
{
    /** category => [colour, lucide icon, price range per unit, unit, pieces per unit] */
    private const CATEGORIES = [
        'Mobil & Kendaraan' => ['#C1603A', 'car', [72000, 180000], 'lusin', 12],
        'Boneka & Figur' => ['#C27BA0', 'smile', [90000, 240000], 'lusin', 12],
        'Edukasi & Puzzle' => ['#5B7DB1', 'puzzle', [48000, 150000], 'lusin', 12],
        'Balok & Rakitan' => ['#F0B90B', 'toy-brick', [65000, 200000], 'pak', 6],
        'Permainan Tradisional' => ['#7A8A5E', 'dices', [30000, 120000], 'lusin', 12],
        'Mainan Luar Ruang' => ['#4E9A8C', 'bike', [42000, 150000], 'lusin', 12],
        'Mainan Bayi' => ['#9C8C67', 'baby', [60000, 180000], 'pak', 6],
    ];

    public function run(): void
    {
        if (Product::forLine(BusinessLine::Toys)->exists()) {
            return;
        }

        $actor = User::where('role', Role::Admin)->first() ?? User::factory()->create(['role' => Role::Admin]);
        $dataDir = database_path('seeders/data');
        $categories = collect(self::CATEGORIES)->map(fn ($c, $name) => Category::firstOrCreate(
            ['name' => $name, 'line' => BusinessLine::Toys->value],
            ['color' => $c[0], 'icon' => $c[1]],
        ));
        $counters = [];

        foreach (json_decode(file_get_contents("{$dataDir}/toys.json"), true) as $row) {
            [, , $range, $unit, $pieces] = self::CATEGORIES[$row['category']];
            mt_srand(crc32($row['slug']));
            $price = (int) (round(mt_rand($range[0], $range[1]) / 500) * 500);
            $stock = mt_rand(20, 150);
            $prefix = Str::upper(Str::substr(Str::slug($row['category'], ''), 0, 3));
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

            $remote = "seed/toys/{$row['image']}";
            Storage::disk('s3')->put($remote, file_get_contents("{$dataDir}/toys/{$row['image']}"));

            $product = Product::create([
                'line' => BusinessLine::Toys,
                'name' => $row['name'],
                'sku' => sprintf('NTY-%s-%03d', $prefix, $counters[$prefix]),
                'category_id' => $categories[$row['category']]->id,
                'price' => $price,
                'wholesale_price' => (int) round($price * 0.88, -2),
                'stock' => $stock,
                'min_stock' => 10,
                'min_purchase' => 1,
                'unit' => $unit,
                'pieces_per_unit' => $pieces,
                'description' => sprintf('%s, dijual grosir per %s (%d pcs) untuk warung dan toko mainan. Foto: %s, %s, via Wikimedia Commons.',
                    $row['name'], $unit, $pieces, $row['author'], $row['licence']),
                'photo_path' => $remote,
                'is_active' => true,
            ]);

            $product->stockMovements()->create([
                'type' => StockMovementType::In, 'quantity' => $stock, 'reference' => 'Stok awal (seed)', 'user_id' => $actor->id,
            ]);
        }
    }
}
