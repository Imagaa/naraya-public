<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_province')->nullable()->after('delivery_address');
            $table->string('delivery_city')->nullable()->after('delivery_address');
            $table->string('delivery_district')->nullable()->after('delivery_address');
            $table->string('delivery_village')->nullable()->after('delivery_address');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['delivery_province', 'delivery_city', 'delivery_district', 'delivery_village']);
        });
    }
};
