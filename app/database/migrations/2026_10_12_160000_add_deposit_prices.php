<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * What a sales person hands the admin for goods sold (user, 2026-10-12): the store pays the sales person the
 * product price, the sales person keeps a cut and deposits products.deposit_price (per unit; empty = the price).
 * Each nota keeps the deposit it owes (sales_notes.deposit_total), so later price changes don't move old debts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->unsignedInteger('deposit_price')->nullable()->after('price');
        });
        Schema::table('sales_notes', function (Blueprint $table) {
            $table->unsignedBigInteger('deposit_total')->default(0)->after('total');
        });
        DB::table('sales_notes')->update(['deposit_total' => DB::raw('total')]);
    }

    public function down(): void
    {
        Schema::table('sales_notes', fn (Blueprint $table) => $table->dropColumn('deposit_total'));
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('deposit_price'));
    }
};
