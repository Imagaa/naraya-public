<?php

namespace Database\Seeders;

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Models\AttendanceLocation;
use App\Models\AttendanceSetting;
use App\Models\BuyerProfile;
use App\Models\Category;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use App\Models\WarehouseSetting;
use App\Notifications\AdminNotice;
use App\Support\Sales\Consignments;
use App\Support\Stock\StockTransfers;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * The sandbox (naraya-coba.example.com, `php artisan sandbox:reset`): the production catalogue with made-up people,
 * so the sales flow can be tried on a real phone and reset at will. Every login uses the password below; the
 * sandbox login page lists them.
 */
class SandboxSeeder extends Seeder
{
    public const PASSWORD = 'coba1234';

    public const ACCOUNTS = [
        ['Admin Coba', 'admin@coba.naraya', Role::Admin],
        ['Sales Coba Satu', 'sales1@coba.naraya', Role::Sales],
        ['Sales Coba Dua', 'sales2@coba.naraya', Role::Sales],
    ];

    /** The production toys catalogue (2026-10-12): name, sku, price and sales deposit per lembar, pcs per lembar, opening stock in pcs. */
    private const PRODUCTS = [
        ['Paket Mainan Lembar Rp.2000-an', 'NRY-TYS-002', 112500, 90000, 75, 15000],
        ['Paket Mainan Lembar Rp3000-an', 'NRY-TYS-003', 120000, 100000, 50, 10000],
        ['Paket Mainan Lembar Rp5000-an', 'NRY-TYS-005', 200000, 165000, 50, 10000],
    ];

    /** Near the Madiun office, where the stores of the sandbox stand. */
    private const OFFICE = [-7.6297, 111.5235];

    public function run(): void
    {
        abort_unless(config('naraya.sandbox'), 403, 'SandboxSeeder hanya untuk server coba.');
        AdminNotice::$muted = true;

        try {
            $users = [];
            foreach (self::ACCOUNTS as [$name, $email, $role]) {
                $users[$email] = User::create(['name' => $name, 'email' => $email, 'password' => Hash::make(self::PASSWORD), 'role' => $role]);
                if ($role === Role::Sales) {
                    SalesProfile::create(['user_id' => $users[$email]->id, 'phone' => '0812-0000-000'.count($users), 'joined_at' => now(), 'monthly_target' => 10000000, 'is_active' => true]);
                }
            }
            $admin = $users['admin@coba.naraya'];
            [$sales1, $sales2] = [$users['sales1@coba.naraya'], $users['sales2@coba.naraya']];

            WarehouseSetting::create(['lat' => self::OFFICE[0], 'lng' => self::OFFICE[1]]);
            AttendanceSetting::current()->update(['max_akurasi_m' => 100]); // indoors on a phone, ±50 m is normal
            // generous check-in circles (the column caps at ~65 km), so absen works wherever the phone is tried
            foreach ([['Area coba Madiun', -7.6297, 111.5235], ['Area coba Solo', -7.5587947, 110.8299272], ['Area coba Jakarta', -6.2, 106.82]] as [$nama, $lat, $lng]) {
                AttendanceLocation::create(['nama' => $nama, 'lat' => $lat, 'lng' => $lng, 'radius_m' => 65000, 'is_active' => true]);
            }

            $category = Category::create(['name' => 'Paket Mainan', 'line' => BusinessLine::Toys, 'color' => '#F97316', 'icon' => 'car']);
            $stock = [];
            foreach (self::PRODUCTS as [$name, $sku, $price, $deposit, $ppu, $pcs]) {
                $product = Product::create([
                    'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'sku' => $sku, 'category_id' => $category->id, 'line' => BusinessLine::Toys,
                    'price' => $price, 'deposit_price' => $deposit, 'unit' => 'lembar', 'pieces_per_unit' => $ppu,
                    'size_value' => 1, 'size_unit' => 'lembar', 'stock' => 0, 'is_active' => true,
                ]);
                $stock[$product->id] = $pcs;
            }
            app(StockTransfers::class)->receive(BusinessLine::Toys, $stock, $admin, 'Stok awal (data coba)');

            // three stores of Sales Coba Satu — one already opened with goods left, ready for a Kunjungan — and one of Dua
            $stores = [
                ['Toko Coba Makmur', 'Pak Andi', 0.004, 0.003, $sales1, true],
                ['Warung Coba Jaya', 'Bu Sari', -0.006, 0.005, $sales1, false],
                ['Kios Coba Sentosa', 'Mas Joko', 0.009, -0.004, $sales1, false],
                ['Toko Coba Lestari', 'Bu Rina', -0.010, -0.008, $sales2, false],
            ];
            foreach ($stores as $i => [$name, $owner, $dLat, $dLng, $sales, $opened]) {
                $store = User::create(['name' => $name, 'role' => Role::User]);
                BuyerProfile::create([
                    'user_id' => $store->id, 'sales_user_id' => $sales->id, 'owner_name' => $owner, 'phone' => '6281200001'.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                    'address' => 'Jl. Coba No. '.($i + 1).', Madiun', 'latitude' => self::OFFICE[0] + $dLat, 'longitude' => self::OFFICE[1] + $dLng, 'is_active' => true,
                ]);
                if ($opened) {
                    app(Consignments::class)->open($sales, $store->id, BusinessLine::Toys, array_map(fn ($ppu) => 2 * $ppu, array_combine(array_keys($stock), array_column(self::PRODUCTS, 4))));
                }
            }
        } finally {
            AdminNotice::$muted = false;
        }

        $this->call(SandboxStoresSeeder::class); // Sales Coba Satu topped up to 50 stores
    }
}
