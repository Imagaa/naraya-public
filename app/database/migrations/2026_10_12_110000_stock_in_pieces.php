<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stock in pcs: a transfer line keeps its rupiah value
 * (pcs × unit price / pcs per unit), since `price` stays the price per unit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->unsignedBigInteger('value')->default(0)->after('price');
        });
        DB::table('stock_transfer_items')->update(['value' => DB::raw('quantity * price')]);
    }

    public function down(): void
    {
        Schema::table('stock_transfer_items', function (Blueprint $table) {
            $table->dropColumn('value');
        });
    }
};
