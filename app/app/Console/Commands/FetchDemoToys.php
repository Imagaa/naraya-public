<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Dev-only: builds the committed Naraya Toys demo catalogue in
 * database/seeders/data/toys* from Wikimedia Commons. Open Products Facts has
 * too few toys (none Indonesian), so the products are a curated list of
 * everyday warung toys and only the photos come from Commons — each with its
 * licence and author recorded for attribution. Seeding never calls the
 * network; only this command does.
 *
 * Commons search is noisy, so an entry may pin an exact `file` after review.
 * An entry whose file is unchanged reuses the photo already downloaded.
 */
class FetchDemoToys extends Command
{
    protected $signature = 'demo:fetch-toys';

    protected $description = 'Fetch photos for the Naraya Toys demo catalogue from Wikimedia Commons';

    private const USER_AGENT = 'NarayaDemoSeeder/1.0 (https://github.com/Imagaa/naraya-public)';

    private const API = 'https://commons.wikimedia.org/w/api.php';

    /** Free licences we accept (prefix match on Commons' LicenseShortName). */
    private const LICENCES = ['CC0', 'Public domain', 'CC BY ', 'CC BY-SA '];

    /** [name, category, Commons search query, pinned file title or null] */
    private const TOYS = [
        ['Mobil-mobilan Plastik', 'Mobil & Kendaraan', 'plastic toy car', 'File:Toy car (AM 1996.165.108-7).jpg'],
        ['Truk Molen Mainan', 'Mobil & Kendaraan', 'toy cement mixer truck', null],
        ['Mobil Pemadam Kebakaran Mainan', 'Mobil & Kendaraan', 'toy fire engine', null],
        ['Bus Mainan', 'Mobil & Kendaraan', 'toy bus', null],
        ['Pesawat Mainan', 'Mobil & Kendaraan', 'toy airplane', 'File:Toy plane (AM 1996.165.128).jpg'],
        ['Kereta Api Mainan', 'Mobil & Kendaraan', 'toy train', 'File:Toy Train Set (Toy Train Set) (48708865722).jpg'],
        ['Motor-motoran Mini', 'Mobil & Kendaraan', 'toy motorcycle', 'File:Shanghai Toy Factory No.5 Tin Toy Siren Friction Chinese Motorcycle with two Liberation Army soldiers (or police) riders Side.jpg'],
        ['Boneka Beruang', 'Boneka & Figur', 'teddy bear', null],
        ['Boneka Kelinci', 'Boneka & Figur', 'stuffed toy rabbit', null],
        ['Boneka Bayi', 'Boneka & Figur', 'baby doll toy', 'File:Baby doll-Calineczka-original-2006.jpg'],
        ['Figur Dinosaurus', 'Boneka & Figur', 'plastic dinosaur toy', null],
        ['Robot Mainan', 'Boneka & Figur', 'toy robot', null],
        ['Puzzle Jigsaw 100 Keping', 'Edukasi & Puzzle', 'jigsaw puzzle pieces', null],
        ['Puzzle Kayu 3D', 'Edukasi & Puzzle', 'wooden puzzle toy', null],
        ['Kubus Rubik 3x3', 'Edukasi & Puzzle', 'Rubik cube', null],
        ['Krayon 12 Warna', 'Edukasi & Puzzle', 'crayons box', 'File:Crayons with colors.JPG'],
        ['Balok Kayu Warna-warni', 'Balok & Rakitan', 'wooden toy blocks', null],
        ['Balok Rakit Plastik', 'Balok & Rakitan', 'plastic interlocking bricks toy', 'File:Toy blocks - Snap 2534.JPG'],
        ['Menara Donat Susun', 'Balok & Rakitan', 'stacking rings toy', null],
        ['Gasing Kayu', 'Permainan Tradisional', 'gasing', 'File:Japanese Top teyorigoma.jpg'],
        ['Kelereng', 'Permainan Tradisional', 'glass marbles', 'File:JM marbles 01.jpg'],
        ['Congklak', 'Permainan Tradisional', 'congklak', null],
        ['Layang-layang', 'Permainan Tradisional', 'layang-layang', 'File:Jules and Gabriel flying a kite at Alameda Afonso Henriques, Lisbon, Portugal (PPL3-Altered) julesvernex2.jpg'],
        ['Ular Tangga', 'Permainan Tradisional', 'snakes and ladders board', null],
        ['Kartu Remi', 'Permainan Tradisional', 'playing cards deck', null],
        ['Halma', 'Permainan Tradisional', 'Chinese checkers board', null],
        ['Pistol Air', 'Mainan Luar Ruang', 'water gun toy', null],
        ['Bola Plastik', 'Mainan Luar Ruang', 'plastic toy ball', 'File:Toy ball.jpg'],
        ['Mainan Gelembung Sabun', 'Mainan Luar Ruang', 'soap bubble wand', null],
        ['Tali Lompat', 'Mainan Luar Ruang', 'skipping rope', null],
        ['Ember Pasir & Sekop', 'Mainan Luar Ruang', 'sand bucket spade toy', null],
        ['Kerincingan Bayi', 'Mainan Bayi', 'baby rattle', null],
        ['Bebek Karet', 'Mainan Bayi', 'rubber duck', 'File:Yellow rubber duck in Rishon Lezion, August 2024 04.jpg'],
        ['Xylophone Mainan', 'Mainan Bayi', 'toy xylophone', null],
    ];

    public function handle(): int
    {
        $dataDir = database_path('seeders/data');
        $imageDir = "{$dataDir}/toys";
        File::ensureDirectoryExists($imageDir);
        $previous = collect(File::exists("{$dataDir}/toys.json") ? json_decode(File::get("{$dataDir}/toys.json"), true) : [])->keyBy('slug');
        $usedTitles = [];
        $manifest = [];

        foreach (self::TOYS as [$name, $category, $query, $pinned]) {
            $slug = Str::slug($name);
            $old = $previous[$slug] ?? null;
            $info = $pinned ? $this->file($pinned) : $this->search($query, $usedTitles);

            if (! $info) {
                $this->warn("  no photo: {$name} ({$query})");

                continue;
            }
            $usedTitles[$info['title']] = true;
            $path = "{$imageDir}/{$slug}.jpg";

            if (! ($old && $old['file'] === $info['title'] && File::exists($path))) {
                $bytes = $this->asJpeg(Http::withUserAgent(self::USER_AGENT)->timeout(60)->retry(3, 2000)->get($info['thumb'])->body());
                if ($bytes === null) {
                    $this->warn("  unreadable photo: {$name}");

                    continue;
                }
                File::put($path, $bytes);
            }

            $manifest[] = [
                'slug' => $slug,
                'name' => $name,
                'category' => $category,
                'image' => "{$slug}.jpg",
                'file' => $info['title'],
                'source' => $info['page'],
                'licence' => $info['licence'],
                'author' => $info['author'],
            ];
            $this->line(sprintf('  [%s] %s ← %s (%s)', $category, $name, $info['title'], $info['licence']));
        }

        File::put("{$dataDir}/toys.json", json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        $this->info(count($manifest).' toys written to database/seeders/data/toys.json');

        return self::SUCCESS;
    }

    /** First search hit that is a free-licensed, roughly square-ish photo not used yet. */
    private function search(string $query, array $used): ?array
    {
        $pages = $this->query(['generator' => 'search', 'gsrsearch' => "filetype:bitmap {$query}", 'gsrnamespace' => 6, 'gsrlimit' => 15]);

        return collect($pages)->sortBy('index')->map(fn ($p) => $this->describe($p))->filter()
            ->first(fn ($i) => ! isset($used[$i['title']]) && $i['ratio'] >= 0.6 && $i['ratio'] <= 1.8 && $i['width'] >= 500);
    }

    private function file(string $title): ?array
    {
        return $this->describe(collect($this->query(['titles' => $title]))->first() ?? []);
    }

    private function query(array $params): array
    {
        $response = Http::withUserAgent(self::USER_AGENT)->timeout(60)->retry(3, 2000)->get(self::API, $params + [
            'action' => 'query', 'format' => 'json', 'prop' => 'imageinfo',
            'iiprop' => 'url|size|mime|extmetadata', 'iiurlwidth' => 600,
            'iiextmetadatafilter' => 'LicenseShortName|Artist',
        ]);

        return $response->json('query.pages') ?? [];
    }

    private function describe(array $page): ?array
    {
        $ii = $page['imageinfo'][0] ?? null;
        $licence = $ii['extmetadata']['LicenseShortName']['value'] ?? '';
        if (! $ii || ! in_array($ii['mime'] ?? '', ['image/jpeg', 'image/png'], true)
            || ! collect(self::LICENCES)->contains(fn ($l) => str_starts_with($licence.' ', $l))) {
            return null;
        }

        return [
            'title' => $page['title'],
            'thumb' => $ii['thumburl'],
            'page' => $ii['descriptionurl'],
            'licence' => $licence,
            'author' => Str::limit(trim(html_entity_decode(strip_tags($ii['extmetadata']['Artist']['value'] ?? 'Tidak diketahui'))), 120),
            'width' => (int) $ii['width'],
            'ratio' => $ii['height'] ? $ii['width'] / $ii['height'] : 0,
        ];
    }

    /** Re-encode (PNG transparency onto white) so every catalogue photo is a plain JPEG. */
    private function asJpeg(string $bytes): ?string
    {
        $src = @imagecreatefromstring($bytes);
        if (! $src) {
            return null;
        }
        $out = imagecreatetruecolor(imagesx($src), imagesy($src));
        imagefill($out, 0, 0, imagecolorallocate($out, 255, 255, 255));
        imagecopy($out, $src, 0, 0, 0, 0, imagesx($src), imagesy($src));
        ob_start();
        imagejpeg($out, null, 80);

        return ob_get_clean();
    }
}
