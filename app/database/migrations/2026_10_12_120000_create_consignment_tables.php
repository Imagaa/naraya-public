<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Titip jual: goods of ours that sit at a store, as a
 * ledger of signed pcs per store and product; a store's first count is marked on its profile; a nota
 * says whether it sold carried goods (bawaan) or consigned ones (titipan).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('consignment_moves', function (Blueprint $table) {
            $table->id();
            $table->foreignId('buyer_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('product_id')->constrained();
            $table->string('line', 16);
            $table->foreignId('sales_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('kind', 16); // awal | titip | terjual | retur
            $table->integer('quantity'); // signed pcs at the store
            $table->foreignId('sales_note_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamps();
            $table->index(['buyer_id', 'line']);
            $table->index(['sales_id', 'kind']);
        });

        Schema::table('buyer_profiles', function (Blueprint $table) {
            $table->timestamp('consignment_opened_at')->nullable();
        });

        Schema::table('sales_notes', function (Blueprint $table) {
            $table->string('source', 16)->default('bawaan')->after('payment_method'); // bawaan | titipan
        });
    }

    public function down(): void
    {
        Schema::table('sales_notes', fn (Blueprint $table) => $table->dropColumn('source'));
        Schema::table('buyer_profiles', fn (Blueprint $table) => $table->dropColumn('consignment_opened_at'));
        Schema::dropIfExists('consignment_moves');
    }
};
