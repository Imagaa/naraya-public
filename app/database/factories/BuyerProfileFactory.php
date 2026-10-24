<?php

namespace Database\Factories;

use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class BuyerProfileFactory extends Factory
{
    protected $model = BuyerProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => Role::User]),
            'phone' => fake()->phoneNumber(),
            'address' => fake()->address(),
            // Complete store data by default, like every real account; tests about the
            // "Lengkapi data toko" gate null these out explicitly.
            'store_photo_path' => 'stores/factory.jpg',
            'latitude' => -6.2,
            'longitude' => 106.8,
            'loyalty_points' => 0,
            'credit_balance' => 0,
            'is_active' => true,
            // An existing store: its first titip-jual count is done. Tests about that gate null it out.
            'consignment_opened_at' => now(),
        ];
    }
}
