<?php

use App\Support\Loyalty\StorePoints;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Points from toys sold. Additive: rupiah_per_point stays, unused.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('buyer_profiles', fn (Blueprint $table) => $table->unsignedInteger('toys_points_awarded')->default(0)->after('loyalty_points'));
        Schema::table('loyalty_settings', fn (Blueprint $table) => $table->unsignedInteger('pcs_per_point')->default(150)->after('rupiah_per_point'));

        app(StorePoints::class)->syncAll(); // backfill: every toys nota recorded so far
    }

    public function down(): void
    {
        Schema::table('loyalty_settings', fn (Blueprint $table) => $table->dropColumn('pcs_per_point'));
        Schema::table('buyer_profiles', fn (Blueprint $table) => $table->dropColumn('toys_points_awarded'));
    }
};
