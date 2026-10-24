<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('slug')->nullable()->unique()->after('name');
            $table->timestamp('discount_ends_at')->nullable()->after('discount_price');
            $table->unsignedInteger('pieces_per_unit')->nullable()->after('unit');
        });

        // Backfill inline (not via the model) so this migration keeps working
        // even if Product's slug logic changes later.
        $used = [];
        foreach (DB::table('products')->orderBy('id')->get(['id', 'name']) as $row) {
            $base = Str::slug($row->name) ?: 'produk';
            $slug = $base;
            $n = 2;
            while (isset($used[$slug])) {
                $slug = "{$base}-{$n}";
                $n++;
            }
            $used[$slug] = true;
            DB::table('products')->where('id', $row->id)->update(['slug' => $slug]);
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropUnique(['slug']);
            $table->dropColumn(['slug', 'discount_ends_at', 'pieces_per_unit']);
        });
    }
};
