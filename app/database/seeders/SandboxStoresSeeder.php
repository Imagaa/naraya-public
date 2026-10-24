<?php

namespace Database\Seeders;

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Product;
use App\Models\StoreSchedule;
use App\Models\User;
use App\Notifications\AdminNotice;
use App\Support\Sales\Consignments;
use Illuminate\Database\Seeder;

/**
 * Sandbox only: fills Sales Coba Satu up to 50 made-up stores around Madiun, to see how a long store list looks in
 * the sales app. Every 4th new store already has goods left (titip jual opened), and the stores get a sample weekly
 * schedule (Mon–Fri) unless one exists. Safe to run again: it only tops up.
 * Run alone: `php artisan db:seed --class=SandboxStoresSeeder --force` (SandboxSeeder calls it on every reset).
 */
class SandboxStoresSeeder extends Seeder
{
    public const TOTAL = 50;

    private const PREFIXES = ['Toko', 'Warung', 'Kios', 'Toko Kelontong'];

    private const NAMES = ['Berkah', 'Makmur', 'Jaya', 'Sentosa', 'Rejeki', 'Abadi', 'Barokah', 'Mulia', 'Lancar', 'Sejahtera', 'Bahagia', 'Murah', 'Maju', 'Amanah', 'Sumber Rezeki'];

    private const OWNERS = ['Pak Budi', 'Bu Sri', 'Mas Agus', 'Bu Wati', 'Pak Slamet', 'Mbak Dewi', 'Pak Joko', 'Bu Yuni', 'Mas Eko', 'Bu Lestari', 'Pak Hadi', 'Mbak Rina'];

    private const STREETS = ['Jl. Pahlawan', 'Jl. Cokroaminoto', 'Jl. Diponegoro', 'Jl. Kolonel Marhadi', 'Jl. Salak', 'Jl. Serayu', 'Jl. Ringin', 'Jl. Panglima Sudirman', 'Jl. Bali', 'Jl. Agus Salim'];

    /** The Madiun office (SandboxSeeder::OFFICE); stores spread up to ~5 km around it. */
    private const CENTRE = [-7.6297, 111.5235];

    public function run(): void
    {
        abort_unless(config('naraya.sandbox'), 403, 'SandboxStoresSeeder hanya untuk server coba.');
        $sales = User::where('email', 'sales1@coba.naraya')->where('role', Role::Sales)->firstOrFail();
        $have = BuyerProfile::where('sales_user_id', $sales->id)->count();
        $products = Product::forLine(BusinessLine::Toys)->pluck('pieces_per_unit', 'id');

        mt_srand(2026); // the same map after every reset
        AdminNotice::$muted = true;
        try {
            for ($i = 0; $have + $i < self::TOTAL; $i++) {
                $name = self::PREFIXES[intdiv($i, count(self::NAMES)) % count(self::PREFIXES)].' '.self::NAMES[$i % count(self::NAMES)];
                $store = User::create(['name' => $name, 'role' => Role::User]);
                BuyerProfile::create([
                    'user_id' => $store->id, 'sales_user_id' => $sales->id, 'owner_name' => self::OWNERS[$i % count(self::OWNERS)],
                    'phone' => '6281200002'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'address' => self::STREETS[$i % count(self::STREETS)].' No. '.mt_rand(1, 120).', Madiun',
                    'latitude' => self::CENTRE[0] + mt_rand(-450, 450) / 10000, 'longitude' => self::CENTRE[1] + mt_rand(-450, 450) / 10000,
                    'is_active' => true,
                ]);
                if ($i % 4 === 0 && $products->isNotEmpty()) {
                    app(Consignments::class)->open($sales, $store->id, BusinessLine::Toys, $products->all()); // one lembar of each
                }
            }
        } finally {
            AdminNotice::$muted = false;
        }

        // a sample weekly schedule, about 10 stores a day Monday to Friday, until the sales person makes their own
        if (! StoreSchedule::where('sales_user_id', $sales->id)->exists()) {
            BuyerProfile::where('sales_user_id', $sales->id)->orderBy('id')->pluck('user_id')
                ->each(fn ($id, $i) => StoreSchedule::create(['sales_user_id' => $sales->id, 'buyer_id' => $id, 'weekday' => $i % 5 + 1]));
        }
    }
}
