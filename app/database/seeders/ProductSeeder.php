<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The demo catalogue: real Indonesian products from Open Food Facts
 * (CC BY-SA), committed under database/seeders/data/ by
 * `php artisan demo:fetch-products`. Every product has its own photo and
 * its real EAN-13 barcode. Prices are invented (the source has none) but
 * deterministic per barcode, so reseeding always gives the same catalogue.
 */
class ProductSeeder extends Seeder
{
    /** category => [price range per unit, unit, pieces per unit, min purchase] */
    private const PRICING = [
        'Mie Instan' => [[95000, 135000], 'karton', 40, 1],
        'Minuman' => [[45000, 110000], 'karton', 24, 1],
        'Kopi & Teh' => [[35000, 120000], 'pak', 10, 2],
        'Susu & Olahan' => [[60000, 180000], 'karton', 24, 1],
        'Snack' => [[25000, 95000], 'pak', 10, 2],
        'Sembako' => [[55000, 240000], 'karton', 12, 1],
        'Bumbu Dapur' => [[30000, 110000], 'pak', 12, 2],
        'Makanan Kaleng & Instan' => [[80000, 260000], 'karton', 24, 1],
        'Perawatan Bayi' => [[70000, 320000], 'pak', 6, 1],
        'Sabun & Toiletries' => [[40000, 160000], 'pak', 12, 1],
        'Kebutuhan Rumah' => [[35000, 150000], 'pak', 12, 1],
        'Lainnya' => [[25000, 120000], 'pak', 12, 1],
    ];

    public function run(): void
    {
        (new CategorySeeder())->run(); // idempotent (firstOrCreate); the manifest needs every category to exist
        $actor = User::where('role', Role::Admin)->first() ?? User::factory()->create(['role' => Role::Admin]);
        $dataDir = database_path('seeders/data');
        $manifest = json_decode(file_get_contents("{$dataDir}/products.json"), true);
        $extras = json_decode(file_get_contents("{$dataDir}/extras.json"), true) ?: [];
        $categoryIds = Category::pluck('id', 'name');
        $counters = [];

        $products = collect($manifest)->map(function (array $row) use ($actor, $dataDir, $categoryIds, &$counters) {
            mt_srand(crc32($row['barcode']));
            [$range, $unit, $pieces, $minPurchase] = self::PRICING[$row['category']];
            $price = (int) (round(mt_rand($range[0], $range[1]) / 500) * 500);
            [$sizeValue, $sizeUnit] = $this->parseSize($row['size']);
            $prefix = Str::upper(Str::substr(Str::slug($row['category'], ''), 0, 3));
            $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

            $remote = "seed/products/{$row['image']}";
            Storage::disk('s3')->put($remote, file_get_contents("{$dataDir}/products/{$row['image']}"));

            $stock = mt_rand(40, 300);
            $product = Product::create([
                'name' => $row['name'],
                'barcode' => $row['barcode'],
                'sku' => sprintf('NRY-%s-%03d', $prefix, $counters[$prefix]),
                'category_id' => $categoryIds[$row['category']],
                'size_value' => $sizeValue,
                'size_unit' => $sizeUnit,
                'price' => $price,
                'wholesale_price' => (int) round($price * 0.86, -2),
                'stock' => $stock,
                'min_stock' => 20,
                'min_purchase' => $minPurchase,
                'unit' => $unit,
                'pieces_per_unit' => $pieces,
                'description' => $this->describe($row),
                'photo_path' => $remote,
                'is_active' => true,
            ]);

            $product->stockMovements()->create([
                'type' => StockMovementType::In, 'quantity' => $stock, 'reference' => 'Stok awal (seed)', 'user_id' => $actor->id,
            ]);

            return $product;
        });

        $this->applyEdgeCases($products->values(), $actor);
        $this->attachExtraPhotos($products, $extras, $dataDir);
    }

    /**
     * Guaranteed catalogue edge cases, assigned by position in a
     * fixed shuffle so every reseed puts them on the same products.
     */
    private function applyEdgeCases($products, User $actor): void
    {
        mt_srand(20261004);
        $order = range(0, $products->count() - 1);
        shuffle($order);
        $take = function (int $n) use (&$order, $products) {
            return collect(array_splice($order, 0, $n))->map(fn ($i) => $products[$i]);
        };

        foreach ($take(8) as $p) { // sold through to zero
            $p->stockMovements()->create(['type' => StockMovementType::Out, 'quantity' => -$p->stock, 'reference' => 'Terjual habis (seed)', 'user_id' => $actor->id]);
            $p->update(['stock' => 0]);
        }

        foreach ($take(4) as $p) { // some stock, but below the minimum order — sold out by the card's rule
            $p->update(['min_purchase' => 5, 'stock' => mt_rand(1, 4)]);
        }

        foreach ($take(8) as $p) { // low stock
            $p->update(['stock' => mt_rand(5, 19)]);
        }

        foreach ($take(6) as $p) { // promo with an end date
            $p->update(['discount_price' => (int) round($p->price * 0.85, -2), 'discount_ends_at' => now()->addDays(mt_rand(2, 14))]);
        }

        foreach ($take(4) as $p) { // promo without an end date
            $p->update(['discount_price' => (int) round($p->price * 0.9, -2), 'discount_ends_at' => null]);
        }

        foreach ($take(3) as $p) { // expired promo — must not show
            $p->update(['discount_price' => (int) round($p->price * 0.8, -2), 'discount_ends_at' => now()->subDays(mt_rand(1, 10))]);
        }

        foreach ($take(2) as $p) { // "discount" at or above price — counts as no discount
            $p->update(['discount_price' => $p->price]);
        }

        foreach ($take(5) as $p) {
            $p->update(['is_active' => false]);
        }

        $products->each(function (Product $p, int $i) {
            if ($i % 3 === 0) {
                $p->update(['pieces_per_unit' => null]);
            }
        });
    }

    /** Extra gallery photos come only from same-brand images that are nobody's cover. */
    private function attachExtraPhotos($products, array $extras, string $dataDir): void
    {
        $attached = 0;

        foreach ($products as $product) {
            $brand = collect(array_keys($extras))->first(fn ($b) => Str::startsWith(Str::lower($product->name), Str::lower($b)));
            if (! $brand || $attached >= 4) {
                continue;
            }

            foreach (array_slice($extras[$brand], 0, 3) as $i => $file) {
                $remote = "seed/products/extra-{$file}";
                Storage::disk('s3')->put($remote, file_get_contents("{$dataDir}/products/{$file}"));
                $product->photos()->create(['path' => $remote, 'sort_order' => $i]);
            }

            unset($extras[$brand]);
            $attached++;
        }
    }

    /** "600 ml" → [600, 'ml'], "1 l" → [1, 'L'], "75g" → [75, 'g']; [null, null] when unparseable. */
    private function parseSize(?string $size): array
    {
        if (! $size || ! preg_match('/(\d+(?:[.,]\d+)?)\s*(ml|l|g|gr|gram|kg|pcs)\b/i', $size, $m)) {
            return [null, null];
        }

        $unit = match (Str::lower($m[2])) {
            'l' => 'L',
            'gr', 'gram' => 'g',
            default => Str::lower($m[2]),
        };

        return [(float) str_replace(',', '.', $m[1]), $unit];
    }

    private function describe(array $row): string
    {
        return trim(sprintf('%s%s. Kategori %s, dijual grosir untuk warung dan toko.',
            $row['name'], $row['size'] ? ' ('.$row['size'].')' : '', Str::lower($row['category'])));
    }
}
