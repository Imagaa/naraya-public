<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * City-center coordinates for the 10 seeded areas — this is a live
     * production data backfill (the areas already exist, created before
     * lat/lng/radius_km existed on this table), not just local seed data,
     * so it has to run as a migration rather than only living in
     * AreaSeeder. Overwrites unconditionally, including Jakarta Pusat's
     * placeholder point from manual UI testing.
     */
    private const GEO = [
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
    ];

    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (self::GEO as $name => $geo) {
            DB::table('areas')->where('name', $name)->update($geo);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::table('areas')->whereIn('name', array_keys(self::GEO))->update([
            'lat' => null,
            'lng' => null,
            'radius_km' => null,
        ]);
    }
};
