<?php

namespace App\Support\Sales;

use App\Enums\Role;
use App\Models\User;
use App\Support\Phone;
use Illuminate\Support\Facades\DB;

/**
 * A sales person registers a store on the spot: no account yet,
 * the login is made from the store page later. Used by the Toko baru sheet and by offline sync.
 */
final class StoreRegistration
{
    public static function create(User $sales, string $name, string $owner, string $phone, string $address, float $lat, float $lng, string $photoPath): User
    {
        return DB::transaction(function () use ($sales, $name, $owner, $phone, $address, $lat, $lng, $photoPath) {
            $user = User::create(['name' => $name, 'role' => Role::User]); // no login until "Buatkan akun"

            // A store a sales signs up is theirs by definition — same area,
            // no picker needed, matching the 1-area-1-sales assignment elsewhere.
            $user->buyerProfile()->create([
                'owner_name' => $owner,
                'phone' => Phone::normalize($phone),
                'address' => $address,
                'latitude' => $lat,
                'longitude' => $lng,
                'store_photo_path' => $photoPath,
                'area_id' => $sales->salesProfile?->area_id,
                'sales_user_id' => $sales->id,
                'is_active' => true,
            ]);

            return $user;
        });
    }
}
