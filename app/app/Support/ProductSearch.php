<?php

namespace App\Support;

use App\Enums\OrderStatus;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Typo-tolerant storefront search over active products (name + category).
 * Every typed word must match a product word: exactly, as a prefix, inside
 * it, through a synonym (config/search.php) or within 1–2 typos. Results
 * rank by match quality, then 90-day sales.
 *
 * ponytail: scans a cached in-memory index, fine to ~10k products; move to
 * Meilisearch (Scout) if the catalogue outgrows that.
 */
class ProductSearch
{
    /** @return array<int, int> product id => score, best match first */
    public static function rank(string $term): array
    {
        $tokens = self::words($term);
        if ($tokens === []) {
            return [];
        }
        $phrase = implode(' ', $tokens);

        $hits = [];
        foreach (self::index() as $id => [$name, $words, $categoryWords, $barcode, $sold]) {
            if ($barcode !== null && $barcode === $phrase) {
                $hits[] = [$id, 10000, $sold, $name];

                continue;
            }

            $score = 0;
            foreach ($tokens as $token) {
                // A word only found in the category name counts for less than one in the product name.
                $best = max(self::tokenScore($token, $words), intdiv(self::tokenScore($token, $categoryWords) * 6, 10));
                if ($best === 0) {
                    continue 2;
                }
                $score += $best;
            }
            if (str_contains($name, $phrase)) {
                $score += 50;
            }
            $hits[] = [$id, $score, $sold, $name];
        }

        usort($hits, fn ($a, $b) => [$b[1], $b[2], $a[3]] <=> [$a[1], $a[2], $b[3]]);

        return array_column($hits, 1, 0);
    }

    /** Lowercased ASCII words, punctuation dropped: "Kopi Kapal-Api 165g" → [kopi, kapal, api, 165g]. */
    public static function words(string $text): array
    {
        return array_values(array_filter(explode(' ', (string) Str::of($text)->ascii()->lower()->replaceMatches('/[^a-z0-9]+/', ' '))));
    }

    private static function tokenScore(string $token, array $words): int
    {
        $synonyms = self::synonyms()[$token] ?? [];
        $best = 0;

        foreach ($words as $word) {
            $best = max($best, self::wordScore($token, $word));
            foreach ($synonyms as $synonym) {
                if ($word === $synonym) {
                    $best = max($best, 90);
                } elseif (strlen($synonym) >= 4 && str_starts_with($word, $synonym)) {
                    $best = max($best, 70);
                }
            }
            if ($best === 100) {
                break;
            }
        }

        return $best;
    }

    private static function wordScore(string $token, string $word): int
    {
        if ($word === $token) {
            return 100;
        }
        if (str_starts_with($word, $token)) {
            return 80;
        }
        $length = strlen($token);
        if ($length >= 3 && str_contains($word, $token)) {
            return 60;
        }
        if ($length < 4) {
            return 0;
        }

        // Typos: one for short words, two from seven letters; also against the
        // start of a longer word, for a mistyped word that's still being typed.
        $allowed = $length >= 7 ? 2 : 1;
        $distance = levenshtein($token, $word);
        if ($distance <= $allowed) {
            return 50 - 10 * $distance;
        }
        if (strlen($word) > $length && levenshtein($token, substr($word, 0, $length)) <= $allowed) {
            return 35;
        }

        return 0;
    }

    /** @return array<string, list<string>> word => the other words in its synonym groups */
    private static function synonyms(): array
    {
        static $map = null;

        if ($map === null) {
            $map = [];
            foreach (config('search.synonyms', []) as $group) {
                foreach ($group as $word) {
                    $map[$word] = array_values(array_unique(array_merge($map[$word] ?? [], array_diff($group, [$word]))));
                }
            }
        }

        return $map;
    }

    /**
     * id => [normalized name, name words, category words, barcode, 90-day units sold]. The key
     * changes whenever a product or category is saved, so edits show up at
     * once; the hour TTL refreshes the sales tie-breaker. Bump the key's v<n>
     * whenever the entry shape changes, or old cached entries break search.
     */
    private static function index(): array
    {
        $version = Product::count().'|'.Product::max('updated_at').'|'.Category::max('updated_at');

        return Cache::remember('product-search:v2:'.\App\Support\ActiveLine::current()->value.':'.md5($version), 3600, function () {
            $sold = DB::table('order_items')
                ->join('orders', 'orders.id', '=', 'order_items.order_id')
                ->where('orders.status', OrderStatus::Completed->value)
                ->where('orders.completed_at', '>=', now()->subDays(90))
                ->groupBy('order_items.product_id')
                ->selectRaw('order_items.product_id, SUM(order_items.quantity) AS sold')
                ->pluck('sold', 'product_id');

            return Product::forLine(\App\Support\ActiveLine::current())->where('is_active', true)->with('category:id,name')->get(['id', 'name', 'barcode', 'category_id'])
                ->mapWithKeys(function (Product $product) use ($sold) {
                    $name = self::words($product->name);

                    return [$product->id => [
                        implode(' ', $name),
                        array_values(array_unique($name)),
                        self::words($product->category?->name ?? ''),
                        $product->barcode ?: null,
                        (int) ($sold[$product->id] ?? 0),
                    ]];
                })->all();
        });
    }
}
