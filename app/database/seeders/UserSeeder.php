<?php

namespace Database\Seeders;

use App\Enums\BuyerStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Staff plus a buyer for every account state
 * (approved, pending, rejected, must change password, incomplete
 * store data, deactivated, no sales). Fixed demo logins all use "password".
 */
class UserSeeder extends Seeder
{
    private array $storePhotos = [];

    private int $storeIndex = 0;

    public function run(): void
    {
        $areas = Area::all();
        $password = Hash::make('password');

        $admin = User::create(['name' => 'Admin Naraya', 'email' => 'admin@naraya.test', 'password' => $password, 'role' => Role::Admin]);
        User::create(['name' => 'Gudang Naraya', 'email' => 'gudang1@naraya.test', 'password' => $password, 'role' => Role::Gudang]);

        $salesPhotoPaths = self::uploadSeedPhotos('sales-profiles', 5);
        $sales1 = User::create(['name' => 'Budi Santoso', 'email' => 'sales1@naraya.test', 'password' => $password, 'role' => Role::Sales]);
        SalesProfile::create([
            'user_id' => $sales1->id, 'area_id' => $areas->first()->id, 'phone' => '0812-8841-2207',
            'photo_path' => $salesPhotoPaths[0], 'joined_at' => now()->subYears(2), 'monthly_target' => 40000000, 'is_active' => true,
        ]);

        // One sales per remaining area except the last (an area with no sales is an edge case).
        $areas->slice(1, 8)->values()->each(function (Area $area, int $i) use ($salesPhotoPaths) {
            $user = User::factory()->create(['role' => Role::Sales]);
            SalesProfile::factory()->create(['user_id' => $user->id, 'area_id' => $area->id, 'photo_path' => $salesPhotoPaths[($i + 1) % 5], 'joined_at' => now()->subMonths(6 + $i), 'is_active' => true]);
        });

        // A deactivated sales who used to cover the first area.
        $retired = User::factory()->create(['role' => Role::Sales, 'name' => 'Agus Pensiun']);
        SalesProfile::factory()->create(['user_id' => $retired->id, 'area_id' => $areas->first()->id, 'photo_path' => $salesPhotoPaths[4], 'joined_at' => now()->subYears(3), 'is_active' => false]);

        $this->storePhotos = $this->uploadStorePhotos();

        $buyer1 = $this->buyer(['name' => 'Toko Sumber Rejeki', 'email' => 'buyer1@naraya.test', 'password' => $password], $areas->first(), [
            'owner_name' => 'Siti Rahayu', 'loyalty_points' => 1240,
        ]);

        $this->buyer([], $areas->last(), ['sales_user_id' => null]); // area with no sales

        // The bulk: approved buyers with complete store data, spread across areas.
        foreach (range(1, 46) as $i) {
            $this->buyer([], $areas[$i % $areas->count()], [
                'loyalty_points' => $i === 46 ? 0 : mt_rand(0, 600), // the last shopping buyer by id: 0 points and (OrderSeeder) no orders
                'credit_balance' => in_array($i, [3, 7], true) ? 25000 * $i : 0,
            ]);
        }

        // Self-registered, waiting for Admin.
        foreach (range(1, 3) as $i) {
            $this->buyer([], $areas[$i], ['status' => BuyerStatus::Pending, 'sales_user_id' => null]);
        }

        // Rejected registrations, with the reason shown on their status page.
        foreach (['Lokasi di luar jangkauan pengiriman', 'Data toko tidak dapat diverifikasi'] as $i => $reason) {
            $this->buyer([], $areas[$i + 4], ['status' => BuyerStatus::Rejected, 'rejection_reason' => $reason, 'sales_user_id' => null]);
        }

        // Created by staff with a temporary password: forced to set their own on first login.
        foreach ([$admin, $sales1] as $issuer) {
            $this->buyer(['must_change_password' => true, 'password_set_by' => $issuer->id], $areas->first());
        }

        // Older accounts missing store data: one has no photo, one has no pin.
        $this->buyer([], $areas[2], ['store_photo_path' => null]);
        $this->buyer([], $areas[3], ['latitude' => null, 'longitude' => null]);

        $this->buyer([], $areas[5], ['is_active' => false]); // deactivated by Admin
    }

    /** Buyers who can actually shop — what Order/Redemption seeders should pick from. */
    public static function shoppingBuyers(): Builder
    {
        return User::query()->where('role', Role::User)->where('must_change_password', false)
            ->whereHas('buyerProfile', fn ($q) => $q->where('status', BuyerStatus::Approved)->where('is_active', true)
                ->whereNotNull('store_photo_path')->whereNotNull('latitude'));
    }

    /**
     * One buyer with a complete store: owner, normalised phone, address, a
     * pin near the area centre, the area derived from that pin, a storefront
     * photo and a matching default address. $profile overrides any of it.
     */
    private function buyer(array $user, Area $area, array $profile = []): User
    {
        $faker = fake('id_ID');
        $lat = round($area->lat + mt_rand(-150, 150) / 10000, 7);
        $lng = round($area->lng + mt_rand(-150, 150) / 10000, 7);
        $street = $faker->streetAddress();

        $buyer = User::create(array_merge([
            'name' => 'Toko '.$faker->lastName().' '.$faker->randomElement(['Jaya', 'Makmur', 'Berkah', 'Sejahtera', 'Abadi', 'Rejeki', 'Lestari']),
            'email' => $faker->unique()->safeEmail(),
            'password' => Hash::make('password'),
            'role' => Role::User,
        ], $user));

        $data = array_merge([
            'owner_name' => $faker->name(),
            'phone' => Phone::normalize('08'.mt_rand(11, 99).mt_rand(1000000, 99999999)),
            'address' => "{$street}, {$area->name}",
            'latitude' => $lat,
            'longitude' => $lng,
            'store_photo_path' => $this->storePhotos[$this->storeIndex++ % count($this->storePhotos)],
            'loyalty_points' => mt_rand(0, 300),
            'credit_balance' => 0,
            'is_active' => true,
            'status' => BuyerStatus::Approved,
        ], $profile);

        $pinArea = $data['latitude'] !== null ? Area::nearestTo($data['latitude'], $data['longitude']) : $area;
        $data['area_id'] = $pinArea?->id;
        $data['sales_user_id'] = array_key_exists('sales_user_id', $profile)
            ? $profile['sales_user_id']
            : $pinArea?->activeSalesProfile()?->user_id;

        $buyer->buyerProfile()->create(['user_id' => $buyer->id] + $data);

        $buyer->addresses()->create([
            'label' => 'Toko',
            'province_id' => '31',
            'province_name' => 'DKI JAKARTA',
            'city_id' => '3171',
            'city_name' => $area->name,
            'district_id' => '3171010',
            'district_name' => $area->name,
            'village_id' => '3171010001',
            'village_name' => $area->name,
            'detail' => $street,
            'lat' => $data['latitude'],
            'lng' => $data['longitude'],
            'is_default' => true,
        ]);

        return $buyer;
    }

    /** @return array<int, string> */
    private function uploadStorePhotos(): array
    {
        return collect(glob(database_path('seeders/data/stores/*.jpg')))->sort()->values()
            ->map(function (string $file) {
                $remote = 'seed/stores/'.basename($file);
                Storage::disk('s3')->put($remote, file_get_contents($file));

                return $remote;
            })->all();
    }

    /**
     * Upload the given number of pre-downloaded local seed photos to S3 and
     * return their stored paths. Shared by every seeder that attaches photos.
     *
     * @return array<int, string>
     */
    public static function uploadSeedPhotos(string $folder, int $count): array
    {
        $paths = [];

        for ($i = 1; $i <= $count; $i++) {
            $localPath = storage_path("app/seed-source-images/{$folder}/{$i}.jpg");
            $remotePath = "seed/{$folder}/{$i}.jpg";

            Storage::disk('s3')->put($remotePath, file_get_contents($localPath));
            $paths[] = $remotePath;
        }

        return $paths;
    }
}
