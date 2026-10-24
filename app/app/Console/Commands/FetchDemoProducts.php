<?php

namespace App\Console\Commands;

use App\Support\Ean13;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Dev-only: builds the committed demo catalogue in database/seeders/data/
 * from Open Food Facts (CC BY-SA). Seeding never calls the network — only
 * this command does, and only when the catalogue needs refreshing.
 *
 * Pulls every Indonesian product with a selected front photo into a local
 * cache, classifies each into one of our categories (category tags first,
 * Indonesian name keywords second), then keeps up to --per-category per
 * category with a valid EAN-13 and a photo no other product shares.
 */
class FetchDemoProducts extends Command
{
    protected $signature = 'demo:fetch-products {--per-category=30} {--refresh-cache}';

    protected $description = 'Fetch the demo product catalogue (names, barcodes, photos) from Open Food Facts';

    private const USER_AGENT = 'NarayaDemoSeeder/1.0 (https://github.com/Imagaa/naraya-public)';

    /** Our category => [OFF category tags, lowercase name keywords]. First match wins, in this order. */
    private const CATEGORIES = [
        'Perawatan Bayi' => [['en:baby-foods', 'en:baby-milks'], ['bayi', 'baby', 'bubur si kecil', 'sgm', 'bebelac', 'promina']],
        'Sabun & Toiletries' => [['en:soaps', 'en:shampoos', 'en:toothpastes'], ['sabun', 'shampo', 'shampoo', 'pasta gigi', 'odol', 'soap', 'sampo', 'deodoran', 'lotion']],
        'Kebutuhan Rumah' => [['en:household-products'], ['deterjen', 'detergent', 'pembersih', 'pewangi', 'tisu', 'tissue', 'obat nyamuk', 'pel lantai']],
        'Mie Instan' => [['en:instant-noodles', 'en:noodles', 'en:asian-noodles'], ['mie ', 'mi goreng', 'mi kuah', 'mi instan', 'noodle', 'ramen', 'bihun', 'kwetiau', 'indomie', 'sarimi', 'supermi']],
        'Kopi & Teh' => [['en:coffees', 'en:instant-coffees', 'en:teas', 'en:tea-based-beverages', 'en:iced-teas'], ['kopi', 'coffee', 'teh ', ' teh', 'tea ', 'cappuccino', 'latte']],
        'Susu & Olahan' => [['en:milks', 'en:dairies', 'en:yogurts', 'en:cheeses', 'en:sweetened-condensed-milks', 'en:dairy-drinks'], ['susu', 'milk', 'yogurt', 'yoghurt', 'keju', 'cheese', 'kental manis']],
        'Minuman' => [['en:waters', 'en:sodas', 'en:juices', 'en:energy-drinks', 'en:soft-drinks', 'en:carbonated-drinks', 'en:beverages'], ['air mineral', 'water', 'minuman', 'jus', 'juice', 'soda', 'isotonic', 'sirup', 'syrup']],
        'Bumbu Dapur' => [['en:sauces', 'en:soy-sauces', 'en:condiments', 'en:spices', 'en:bouillon-cubes', 'en:seasonings'], ['kecap', 'saus', 'sauce', 'sambal', 'bumbu', 'kaldu', 'penyedap', 'masako', 'royco', 'merica', 'lada']],
        'Makanan Kaleng & Instan' => [['en:canned-foods', 'en:canned-fishes', 'en:sardines', 'en:corned-beef', 'en:instant-meals', 'en:meals'], ['kaleng', 'sarden', 'sardine', 'kornet', 'corned', 'abon', 'rendang', 'bubur instan']],
        'Sembako' => [['en:rices', 'en:sugars', 'en:vegetable-oils', 'en:flours', 'en:salts', 'en:eggs', 'en:cooking-oils'], ['beras', 'gula', 'sugar', 'minyak goreng', 'cooking oil', 'tepung', 'flour', 'garam', 'telur', 'margarin', 'mentega']],
        'Snack' => [['en:snacks', 'en:biscuits', 'en:chips-and-fries', 'en:wafers', 'en:crackers', 'en:candies', 'en:chocolates', 'en:confectioneries', 'en:sweet-snacks', 'en:salty-snacks'], ['keripik', 'chips', 'biskuit', 'biscuit', 'wafer', 'coklat', 'chocolate', 'permen', 'candy', 'snack', 'kacang', 'crackers', 'kue']],
    ];

    public function handle(): int
    {
        $dataDir = database_path('seeders/data');
        $imageDir = "{$dataDir}/products";
        File::ensureDirectoryExists($imageDir);

        $raw = $this->loadOrFetchCache();
        $this->info('Cached Indonesian products with a front photo: '.count($raw));

        $perCategory = (int) $this->option('per-category');
        $picked = array_fill_keys(array_keys(self::CATEGORIES), []);
        $spares = [];
        $seenBarcodes = [];
        $seenImages = [];
        $seenNames = [];

        // Best-curated records first: the most complete entries, then the most scanned (well-known) products.
        usort($raw, fn ($a, $b) => [($b['completeness'] ?? 0), ($b['unique_scans_n'] ?? 0)] <=> [($a['completeness'] ?? 0), ($a['unique_scans_n'] ?? 0)]);

        foreach ($raw as $p) {
            $code = (string) ($p['code'] ?? '');
            $name = $this->cleanName($p);
            $category = $this->classify($p, $name);
            $nameKey = preg_replace('/[^a-z0-9]/', '', Str::lower($name));

            if (! $category || ! Ean13::isValid($code) || isset($seenBarcodes[$code]) || isset($seenNames[$nameKey])
                || ! $this->isPresentableName($name) || empty($p['image_front_url'])) {
                continue;
            }

            $full = count($picked[$category]) >= $perCategory;
            $brand = $this->brand($p);

            // Once a category is full, still collect a few same-brand spares (12 in total, enough
            // for 4 products' extra gallery photos) — never one per brand across the whole cache.
            $spareCount = array_sum(array_map('count', $spares));
            if ($full && (! $brand || $spareCount >= 12 || count($spares[$brand] ?? []) >= 3 || ! $this->hasCoverForBrand($picked, $brand))) {
                continue;
            }

            // Reuse an image already fetched by an earlier run instead of downloading it again.
            $existing = "{$imageDir}/{$code}.jpg";
            $bytes = File::exists($existing) ? File::get($existing) : $this->downloadResized($p['image_front_url']);
            if ($bytes === null) {
                continue;
            }

            $hash = sha1($bytes);
            if (isset($seenImages[$hash])) {
                continue;
            }

            $seenBarcodes[$code] = true;
            $seenImages[$hash] = true;
            File::put("{$imageDir}/{$code}.jpg", $bytes);

            if ($full) {
                $spares[$brand][] = "{$code}.jpg";
                continue;
            }

            $seenNames[$nameKey] = true;
            $picked[$category][] = [
                'barcode' => $code,
                'name' => $name,
                'brand' => $brand,
                'size' => trim((string) ($p['quantity'] ?? '')) ?: null,
                'category' => $category,
                'image' => "{$code}.jpg",
                'source' => "https://world.openfoodfacts.org/product/{$code}",
            ];
            $this->line(sprintf('  [%s] %s', $category, $name));
        }

        $manifest = collect($picked)->flatten(1)->sortBy(['category', 'name'])->values()->all();
        File::put("{$dataDir}/products.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        File::put("{$dataDir}/extras.json", json_encode($spares, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

        foreach ($picked as $category => $rows) {
            $this->info(sprintf('%-26s %d', $category, count($rows)));
        }
        $this->info('Total: '.count($manifest));

        return self::SUCCESS;
    }

    /**
     * Contributor-typed names vary wildly; keep only ones a buyer can read:
     * 2+ words, no vowel-less shorthand ("mdu", "tpng"), no company name
     * standing in for the product ("PT …"), and no pork (halal market).
     */
    private function isPresentableName(string $name): bool
    {
        $lower = ' '.Str::lower($name).' ';

        if (mb_strlen($name) > 70 || str_word_count(preg_replace('/[^\pL\s]/u', ' ', $name)) < 2) {
            return false;
        }

        foreach ([' pt ', ' pt.', 'pt.', ' cv ', 'pork', ' babi', ' lard', ' ham ', 'bacon'] as $bad) {
            if (str_starts_with(trim($lower), trim($bad)) || str_contains($lower, $bad)) {
                return false;
            }
        }

        foreach (preg_split('/[^a-z]+/', trim(Str::lower($name))) as $word) {
            if (strlen($word) >= 3 && ! preg_match('/[aeiouy]/', $word)) {
                return false; // shorthand like "mdu", "klg", "kntcy"
            }
        }

        return true;
    }

    /** A spare is only useful when a picked product of the same brand can show it in its gallery. */
    private function hasCoverForBrand(array $picked, string $brand): bool
    {
        foreach ($picked as $rows) {
            foreach ($rows as $row) {
                if ($row['brand'] === $brand) {
                    return true;
                }
            }
        }

        return false;
    }

    /** @return array<int, array<string, mixed>> */
    private function loadOrFetchCache(): array
    {
        $cache = storage_path('app/off-indonesia-cache.json');

        if (File::exists($cache) && ! $this->option('refresh-cache')) {
            return json_decode(File::get($cache), true);
        }

        // Resumable: a partial cache (".part") keeps finished pages if the run dies mid-way.
        $part = "{$cache}.part";
        $all = File::exists($part) ? json_decode(File::get($part), true) : [];
        for ($page = intdiv(count($all), 100) + 1; ; $page++) {
            // search-a-licious (Elasticsearch) rather than /api/v2/search, which
            // is throttled to ~10 requests/minute and often answers 503.
            $response = Http::withUserAgent(self::USER_AGENT)->timeout(120)
                ->retry(6, fn (int $attempt) => 10000 * $attempt, throw: true)
                ->get('https://search.openfoodfacts.org/search', [
                    'q' => 'countries_tags:"en:indonesia" AND states_tags:"en:front-photo-selected"',
                    'fields' => 'code,product_name,product_name_id,product_name_en,lang,brands,quantity,categories_tags,image_front_url,completeness,unique_scans_n',
                    'page_size' => 100,
                    'page' => $page,
                ]);

            $products = $response->json('hits') ?? [];
            $all = array_merge($all, $products);
            $this->line("page {$page}/".$response->json('page_count').': '.count($products).' (total '.count($all).')');

            File::put($part, json_encode($all));

            if ($products === [] || $page >= (int) $response->json('page_count')) {
                break;
            }

            usleep(1_000_000);
        }

        File::put($cache, json_encode($all));
        File::delete($part);

        return $all;
    }

    private function classify(array $p, string $name): ?string
    {
        $tags = $p['categories_tags'] ?? [];
        $haystack = ' '.Str::lower($name).' ';

        // Medicines and supplements aren't grosir warung stock.
        foreach (['lozenge', 'betadine', 'vitamin', 'suplemen', 'supplement', 'tablet', 'kapsul', 'capsule', 'obat ', 'herbal medicine'] as $excluded) {
            if (str_contains($haystack, $excluded) || in_array('en:dietary-supplements', $tags, true)) {
                return null;
            }
        }

        // The Indonesian name is more specific than the category tags ("Kopi Susu" tagged as a snack), so it wins.
        foreach (self::CATEGORIES as $category => [$offTags, $keywords]) {
            foreach ($keywords as $keyword) {
                if (str_contains($haystack, $keyword)) {
                    return $category;
                }
            }
        }

        foreach (self::CATEGORIES as $category => [$offTags, $keywords]) {
            if (array_intersect($offTags, $tags)) {
                return $category;
            }
        }

        return null;
    }

    private function cleanName(array $p): string
    {
        // Local products only: an Indonesian name, or a record whose own language is Indonesian.
        // English-only records in the Indonesia list are mostly imports a warung wouldn't stock.
        $local = (string) ($p['product_name_id'] ?? '') ?: (($p['lang'] ?? null) === 'id' ? (string) ($p['product_name'] ?? '') : '');
        // Drop retail shelf codes contributors copied from the label ("E-1B").
        $name = preg_replace(['/\bE-\d+\w*\b/u', '/\s+/'], ['', ' '], trim($local));
        $name = trim($name);

        if ($name === '' || str_contains($name, "\u{FFFD}")) {
            return ''; // never let the brand alone stand in for a product name; skip mangled text
        }

        if ($name === mb_strtoupper($name) || $name === mb_strtolower($name)) {
            $name = Str::title(Str::lower($name));
        }

        // Prefix the brand only when it's properly written and none of its words are already in the
        // name — otherwise contributor-typed manufacturers ("indofood", "ultrajaya") clutter it.
        $brand = $this->brand($p);
        $brandWords = array_filter(preg_split('/\s+/', Str::lower((string) $brand)), fn ($w) => strlen($w) >= 3);
        $named = collect($brandWords)->contains(fn ($w) => str_contains(Str::lower($name), $w));
        if ($brand && ! $named && ctype_upper(mb_substr($brand, 0, 1)) && str_word_count($name) <= 4) {
            $name = "{$brand} {$name}";
        }

        return trim($name);
    }

    private function brand(array $p): ?string
    {
        $brands = $p['brands'] ?? '';
        $brand = trim(is_array($brands) ? (string) ($brands[0] ?? '') : explode(',', (string) $brands)[0]);

        return $brand === '' ? null : (mb_strtoupper($brand) === $brand ? Str::title(Str::lower($brand)) : $brand);
    }

    /** Download (preferring the full-size image), fit to 600px wide on white, JPEG q78. Null when unusable. */
    private function downloadResized(string $url): ?string
    {
        foreach ([preg_replace('/\.\d+\.jpg$/', '.full.jpg', $url), $url] as $candidate) {
            try {
                $response = Http::withUserAgent(self::USER_AGENT)->timeout(60)->retry(2, 2000)->get($candidate);
            } catch (\Throwable) {
                continue;
            }

            $src = $response->successful() ? @imagecreatefromstring($response->body()) : false;
            if (! $src || imagesx($src) < 300) {
                continue;
            }

            $w = 600;
            $h = (int) round(imagesy($src) * $w / imagesx($src));
            $dst = imagecreatetruecolor($w, $h);
            imagefill($dst, 0, 0, imagecolorallocate($dst, 255, 255, 255));
            imagecopyresampled($dst, $src, 0, 0, 0, 0, $w, $h, imagesx($src), imagesy($src));

            ob_start();
            imagejpeg($dst, null, 78);

            return ob_get_clean();
        }

        return null;
    }
}
