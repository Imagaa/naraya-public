<?php

namespace App\Console\Commands;

use App\Enums\BusinessLine;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Deletes every grosir (line naraya) product and every row that points at one, before grosir opens
 *. Orders, notas and stock transfers left without
 * items go too. Nothing of Naraya Toys changes: a record that mixes both lines stops the run.
 */
class PurgeGrosirProducts extends Command
{
    protected $signature = 'grosir:purge-products {--force : Hapus sungguhan (tanpa ini hanya uji coba)}';

    protected $description = 'Hapus semua produk grosir beserta catatannya; Naraya Toys tidak disentuh';

    /** item table => [its parent key, the parent table] */
    private const PARENTS = [
        'order_items' => ['order_id', 'orders'],
        'sales_note_items' => ['sales_note_id', 'sales_notes'],
        'stock_transfer_items' => ['stock_transfer_id', 'stock_transfers'],
    ];

    public function handle(): int
    {
        $ids = Product::where('line', BusinessLine::Naraya)->pluck('id');
        $children = $this->productReferences();

        $mixed = $this->mixedParents($ids);
        if ($mixed->isNotEmpty()) {
            $this->error('Dibatalkan, tidak ada yang dihapus: catatan ini berisi barang grosir dan Toys sekaligus — '.$mixed->implode(', '));

            return self::FAILURE;
        }

        $parents = collect(self::PARENTS)->mapWithKeys(fn ($p, $items) => [$p[1] => DB::table($items)->whereIn('product_id', $ids)->distinct()->pluck($p[0])]);

        $this->line('products: '.$ids->count());
        foreach ($children as [$table, $column]) {
            $this->line("{$table}: ".DB::table($table)->whereIn($column, $ids)->count());
        }
        foreach ($parents as $table => $parentIds) {
            $this->line("{$table} (tanpa barang tersisa): ".$parentIds->count());
        }

        if (! $this->option('force')) {
            $this->info('Uji coba — tidak ada yang dihapus. Jalankan dengan --force untuk menghapus.');

            return self::SUCCESS;
        }

        DB::transaction(function () use ($ids, $children, $parents) {
            foreach ($children as [$table, $column]) {
                DB::table($table)->whereIn($column, $ids)->delete();
            }
            foreach ($parents as $table => $parentIds) {
                DB::table($table)->whereIn('id', $parentIds)->delete();
            }
            Product::whereKey($ids)->delete();
        });
        $this->info('Selesai: '.$ids->count().' produk grosir dan catatannya dihapus.');

        return self::SUCCESS;
    }

    /** @return Collection<int, array{0: string, 1: string}> every [table, column] with a foreign key to products */
    private function productReferences(): Collection
    {
        // this database only: on the VPS the sandbox (naraya_coba) lives in the same MySQL server
        return collect(Schema::getTables(Schema::getCurrentSchemaName()))->pluck('name')->unique()
            ->flatMap(fn (string $table) => collect(Schema::getForeignKeys($table))
                ->filter(fn (array $fk) => $fk['foreign_table'] === 'products')
                ->map(fn (array $fk) => [$table, $fk['columns'][0]]))
            ->values();
    }

    /** @return Collection<int, string> "orders #12"-style labels of parents holding both grosir and other items */
    private function mixedParents(Collection $ids): Collection
    {
        return collect(self::PARENTS)->flatMap(function (array $parent, string $items) use ($ids) {
            [$key, $table] = $parent;
            $withGrosir = DB::table($items)->whereIn('product_id', $ids)->distinct()->pluck($key);
            $withOthers = DB::table($items)->whereIn($key, $withGrosir)->whereNotIn('product_id', $ids)->distinct()->pluck($key);

            return $withOthers->map(fn ($id) => "{$table} #{$id}");
        })->values();
    }
}
