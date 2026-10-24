<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('category_id')->nullable()->after('category')->constrained()->nullOnDelete();
            $table->string('sku')->nullable()->unique()->after('id');
            $table->decimal('size_value', 8, 2)->nullable()->after('size');
            $table->string('size_unit', 20)->nullable()->after('size_value');
        });

        $this->backfillCategories();
        $this->backfillSize();

        // category_id fully replaces the free-text category column going
        // forward — the form will only ever write to category_id now.
        // size is left in place (not dropped): the regex parse below is
        // best-effort on free text and won't always succeed, so the
        // original string stays as a fallback/audit trail until it's been
        // spot-checked against real data.
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    private function backfillCategories(): void
    {
        // Same rotation App\Support\ProductCategories::tagVariant() used
        // (crc32 hash mod variant count) so colors stay identical to what
        // was already showing on the storefront before this migration.
        $variants = ['accent', 'accent-2', 'success', 'neutral', 'danger'];
        $icons = [
            'Mie Instan' => 'soup',
            'Minuman' => 'cup-soda',
            'Sembako' => 'shopping-bag',
            'Snack' => 'cookie',
            'Kebutuhan Rumah' => 'home',
            'Lainnya' => 'more-horizontal',
            'Sabun & Toiletries' => 'soap-dispenser-droplet',
            'Bumbu Dapur' => 'chef-hat',
        ];

        $names = DB::table('products')->whereNotNull('category')->distinct()->pluck('category');

        foreach ($names as $name) {
            $id = DB::table('categories')->where('name', $name)->value('id');

            if (! $id) {
                $id = DB::table('categories')->insertGetId([
                    'name' => $name,
                    'color' => $variants[crc32($name) % count($variants)],
                    'icon' => $icons[$name] ?? 'more-horizontal',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }

            DB::table('products')->where('category', $name)->update(['category_id' => $id]);
        }
    }

    private function backfillSize(): void
    {
        $rows = DB::table('products')->whereNotNull('size')->select('id', 'size')->get();

        foreach ($rows as $row) {
            if (preg_match('/^\s*(\d+(?:[.,]\d+)?)\s*([a-zA-Z]+)\s*$/', $row->size, $m)) {
                DB::table('products')->where('id', $row->id)->update([
                    'size_value' => (float) str_replace(',', '.', $m[1]),
                    'size_unit' => strtolower($m[2]),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('category')->nullable()->after('sku');
        });

        DB::table('products')->update(['category' => null]);
        foreach (DB::table('products')->whereNotNull('category_id')->select('id', 'category_id')->get() as $row) {
            $name = DB::table('categories')->where('id', $row->category_id)->value('name');
            DB::table('products')->where('id', $row->id)->update(['category' => $name]);
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn(['sku', 'size_value', 'size_unit']);
        });
    }
};
