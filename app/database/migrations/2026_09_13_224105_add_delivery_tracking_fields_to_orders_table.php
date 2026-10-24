<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dateTime('estimated_delivery_at')->nullable()->after('delivery_village');
            $table->string('delivery_proof_path')->nullable()->after('estimated_delivery_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['estimated_delivery_at', 'delivery_proof_path']);
        });
    }
};
