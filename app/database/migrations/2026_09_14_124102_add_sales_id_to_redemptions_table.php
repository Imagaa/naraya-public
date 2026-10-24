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
        Schema::table('redemptions', function (Blueprint $table) {
            $table->foreignId('sales_id')->nullable()->after('diproses_oleh')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('redemptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('sales_id');
        });
    }
};
