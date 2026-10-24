<?php

namespace Database\Factories;

use App\Models\Address;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AddressFactory extends Factory
{
    protected $model = Address::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => 'Rumah',
            'province_id' => '33',
            'province_name' => 'Jawa Tengah',
            'city_id' => '3374',
            'city_name' => 'Kota Semarang',
            'district_id' => '3374010',
            'district_name' => 'Semarang Tengah',
            'village_id' => '3374010001',
            'village_name' => 'Bumikasi',
            'detail' => fake()->streetAddress(),
            'postal_code' => fake()->postcode(),
            'lat' => -6.9667,
            'lng' => 110.4167,
            'is_default' => true,
        ];
    }
}
