<?php

namespace Database\Seeders;

use App\Models\Area;
use Illuminate\Database\Seeder;

class AreaSeeder extends Seeder
{
    public function run(): void
    {
        collect([
            'Jakarta Pusat' => ['lat' => -6.1753942, 'lng' => 106.8271528, 'radius_km' => 5],
            'Jakarta Selatan' => ['lat' => -6.2443, 'lng' => 106.7977, 'radius_km' => 6],
            'Jakarta Barat' => ['lat' => -6.1674, 'lng' => 106.7637, 'radius_km' => 6],
            'Jakarta Timur' => ['lat' => -6.2250, 'lng' => 106.8951, 'radius_km' => 6],
            'Jakarta Utara' => ['lat' => -6.1214, 'lng' => 106.8926, 'radius_km' => 6],
            'Bekasi' => ['lat' => -6.2349, 'lng' => 106.9896, 'radius_km' => 8],
            'Tangerang' => ['lat' => -6.1783, 'lng' => 106.6319, 'radius_km' => 8],
            'Depok' => ['lat' => -6.4025, 'lng' => 106.7942, 'radius_km' => 6],
            'Bogor' => ['lat' => -6.5950, 'lng' => 106.8166, 'radius_km' => 8],
            'Bandung' => ['lat' => -6.9175, 'lng' => 107.6191, 'radius_km' => 10],
        ])->each(fn (array $geo, string $name) => Area::create(['name' => $name, ...$geo]));
    }
}
