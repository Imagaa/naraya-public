<?php

namespace App\Support\Stock;

use App\Enums\BusinessLine;
use App\Enums\StockMovementType;
use App\Notifications\AdminNotice;
use App\Enums\StockTransferKind;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockTransfer;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Every goods movement between the warehouse and the sales people.
 * Each call is all-or-nothing: one DB transaction, and
 * for a sales person one cache lock, so two taps can't both pass the caps.
 * The warehouse (products.stock) only changes through Product::adjustStock().
 */
final class StockTransfers
{
    /** @param array<int, int> $items product_id => quantity */
    public function receive(BusinessLine $line, array $items, User $admin, ?string $note = null): StockTransfer
    {
        $products = $this->products($line, $items);

        return DB::transaction(function () use ($line, $items, $admin, $note, $products) {
            $transfer = $this->record(StockTransferKind::Masuk, $line, null, $admin, $note, $items, $products);
            foreach ($items as $id => $qty) {
                $products[$id]->adjustStock($qty, StockMovementType::In, $admin, 'Barang masuk', "transfer:{$transfer->id}");
            }

            return $transfer->load('items');
        });
    }

    /** @param array<int, int> $items product_id => quantity */
    public function pickup(User $sales, BusinessLine $line, array $items, ?string $note = null): StockTransfer
    {
        $products = $this->products($line, $items);

        return $this->locked($sales, fn () => DB::transaction(function () use ($sales, $line, $items, $note, $products) {
            $transfer = $this->record(StockTransferKind::Ambil, $line, $sales, $sales, $note, $items, $products);
            foreach ($items as $id => $qty) {
                try {
                    $products[$id]->adjustStock(-$qty, StockMovementType::Out, $sales, 'Diambil sales', "transfer:{$transfer->id}");
                } catch (InsufficientStockException) {
                    throw ValidationException::withMessages([
                        "items.{$id}" => "{$products[$id]->name}: stok gudang tinggal {$products[$id]->qtyLabel((int) $products[$id]->fresh()->stock)}.",
                    ]);
                }
            }

            return $transfer->load('items');
        }));
    }

    /** @param array<int, int> $items product_id => quantity */
    public function returnGoods(User $sales, BusinessLine $line, array $items, ?string $note = null): StockTransfer
    {
        $products = $this->products($line, $items);

        return $this->locked($sales, fn () => DB::transaction(function () use ($sales, $line, $items, $note, $products) {
            $carried = CarriedStock::for($sales, $line);
            foreach ($items as $id => $qty) {
                if ($qty > ($carried[$id] ?? 0)) {
                    throw ValidationException::withMessages([
                        "items.{$id}" => "{$products[$id]->name}: yang dibawa hanya ".$products[$id]->qtyLabel($carried[$id] ?? 0).'.',
                    ]);
                }
            }

            $transfer = $this->record(StockTransferKind::Retur, $line, $sales, $sales, $note, $items, $products);
            foreach ($items as $id => $qty) {
                $products[$id]->adjustStock($qty, StockMovementType::In, $sales, 'Retur dari sales', "transfer:{$transfer->id}");
            }

            return $transfer->load('items');
        }));
    }

    /**
     * Store returns (titip jual) the sales person hands to the warehouse at check-out. Recorded, but not
     * added to sellable stock: admin books whatever is still sellable through "Barang masuk".
     *
     * @param array<int, int> $items product_id => pcs
     */
    public function handInStoreReturns(User $sales, BusinessLine $line, array $items): StockTransfer
    {
        $products = $this->products($line, $items);

        return $this->record(StockTransferKind::ReturToko, $line, $sales, $sales, 'Retur dari toko, diserahkan saat absen pulang', $items, $products)->load('items');
    }

    /**
     * Admin counts what a sales person really carries. Every counted quantity
     * below the record becomes a shortfall item; the warehouse is untouched.
     * Returns null when everything matches.
     *
     * @param array<int, int> $counted product_id => counted quantity (≥ 0)
     */
    public function stockCheck(User $sales, BusinessLine $line, array $counted, User $admin, ?string $note = null): ?StockTransfer
    {
        return $this->locked($sales, fn () => DB::transaction(function () use ($sales, $line, $counted, $admin, $note) {
            $carried = CarriedStock::for($sales, $line);
            $shortfall = [];
            foreach ($counted as $id => $qty) {
                $qty = (int) $qty;
                $recorded = $carried[$id] ?? 0;
                if ($qty < 0) {
                    throw ValidationException::withMessages(["items.{$id}" => 'Jumlah tidak boleh negatif.']);
                }
                if ($qty > $recorded) {
                    throw ValidationException::withMessages(["items.{$id}" => "Lebih dari catatan ({$recorded}) — catat sebagai pengambilan."]);
                }
                if ($qty < $recorded) {
                    $shortfall[$id] = $recorded - $qty;
                }
            }

            if ($shortfall === []) {
                return null;
            }

            $products = $this->products($line, $shortfall);
            $transfer = $this->record(StockTransferKind::Opname, $line, $sales, $admin, $note, $shortfall, $products)->load('items');
            AdminNotice::toAdmins('Selisih cek stok', "{$sales->name} kurang ".array_sum($shortfall).' pcs', route('admin.transfers.index', absolute: false), 'package-x', 'warning');

            return $transfer;
        }));
    }

    /** @param array<int, int> $items @return Collection<int, Product> keyed by id */
    private function products(BusinessLine $line, array $items): Collection
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => 'Pilih minimal satu barang.']);
        }
        foreach ($items as $id => $qty) {
            if (! is_int($qty) && ! ctype_digit((string) $qty) || (int) $qty < 1) {
                throw ValidationException::withMessages(["items.{$id}" => 'Jumlah minimal 1.']);
            }
        }

        $products = Product::forLine($line)->whereKey(array_keys($items))->get()->keyBy('id');
        if ($products->count() !== count($items)) {
            throw ValidationException::withMessages(['items' => 'Ada barang yang bukan dari lini '.$line->label().'.']);
        }

        return $products;
    }

    /** @param array<int, int> $items @param Collection<int, Product> $products */
    private function record(StockTransferKind $kind, BusinessLine $line, ?User $sales, User $by, ?string $note, array $items, Collection $products): StockTransfer
    {
        $transfer = StockTransfer::create([
            'line' => $line,
            'kind' => $kind,
            'sales_id' => $sales?->id,
            'note' => $note ?: null,
            'created_by' => $by->id,
        ]);
        foreach ($items as $id => $qty) {
            $transfer->items()->create(['product_id' => $id, 'quantity' => (int) $qty, 'price' => (int) $products[$id]->price, 'value' => $products[$id]->valueOf((int) $qty)]);
        }

        return $transfer;
    }

    private function locked(User $sales, callable $work): mixed
    {
        return SalesStockLock::run($sales, $work);
    }
}
