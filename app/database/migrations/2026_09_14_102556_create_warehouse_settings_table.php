<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('warehouse_settings', function (Blueprint $table) {
            $table->id();
            $table->string('address')->nullable();
            $table->decimal('lat', 10, 7);
            $table->decimal('lng', 10, 7);
            $table->timestamps();
        });

        // Jakarta Pusat (Monas) as a reasonable starting default — covers the
        // current Jabodetabek+Bandung operating area at a coarse level. Admin
        // should update this to the real warehouse location.
        DB::table('warehouse_settings')->insert([
            'address' => null,
            'lat' => -6.1753942,
            'lng' => 106.8271528,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('warehouse_settings');
    }
};
