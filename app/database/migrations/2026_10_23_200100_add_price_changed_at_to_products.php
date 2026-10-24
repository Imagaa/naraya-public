<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// When a product's price last changed, shown on grosir product pages.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->timestamp('price_changed_at')->nullable()->after('discount_ends_at'));
        DB::table('products')->update(['price_changed_at' => DB::raw('updated_at')]);
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $table) => $table->dropColumn('price_changed_at'));
    }
};
