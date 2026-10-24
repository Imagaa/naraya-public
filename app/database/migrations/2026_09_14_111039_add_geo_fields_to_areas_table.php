<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->decimal('lat', 10, 7)->nullable()->after('name');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
            $table->unsignedInteger('radius_km')->nullable()->after('lng');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('areas', function (Blueprint $table) {
            $table->dropColumn(['lat', 'lng', 'radius_km']);
        });
    }
};
