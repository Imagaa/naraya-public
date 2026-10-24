<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A store's first purchase skips the first month: payouts start in month 2 and run 11 times (rule of 2026-10-11). */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table) {
            $table->unsignedTinyInteger('skipped_months')->default(0)->after('months');
        });
    }

    public function down(): void
    {
        Schema::table('investment_purchases', function (Blueprint $table) {
            $table->dropColumn('skipped_months');
        });
    }
};
