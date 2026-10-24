<?php

namespace App\Support\Stock;

use App\Enums\BusinessLine;
use App\Enums\StockTransferKind;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What a sales person is carrying, per product, in pcs: picked up − returned − stock-check shortfall − sold on
 * their own notes (bawaan) − left at stores (titip). Computed every time, never stored, so it cannot drift.
 */
final class CarriedStock
{
    /** @return Collection<int, int> product_id => quantity (> 0 only), ordered by product id */
    public static function for(User $sales, BusinessLine $line): Collection
    {
        $signed = DB::table('stock_transfer_items')
            ->join('stock_transfers', 'stock_transfers.id', '=', 'stock_transfer_items.stock_transfer_id')
            ->where('stock_transfers.sales_id', $sales->id)
            ->where('stock_transfers.line', $line->value)
            ->where('stock_transfers.kind', '!=', StockTransferKind::ReturToko->value) // store returns are held apart (Consignments::returnsHeld)
            ->groupBy('stock_transfer_items.product_id')
            ->selectRaw('stock_transfer_items.product_id as product_id, SUM(CASE WHEN stock_transfers.kind = ? THEN stock_transfer_items.quantity ELSE -stock_transfer_items.quantity END) as qty', [StockTransferKind::Ambil->value])
            ->pluck('qty', 'product_id')
            ->map(fn ($q) => (int) $q);

        foreach (self::sold($sales, $line) as $productId => $qty) {
            $signed[$productId] = ($signed[$productId] ?? 0) - $qty;
        }
        foreach (self::leftAtStores($sales, $line) as $productId => $qty) {
            $signed[$productId] = ($signed[$productId] ?? 0) - $qty;
        }

        return $signed->filter(fn (int $q) => $q > 0)->sortKeys();
    }

    /** @return Collection<int, int> product_id => quantity sold on the sales person's notes in this line */
    private static function sold(User $sales, BusinessLine $line): Collection
    {
        return DB::table('sales_note_items')
            ->join('sales_notes', 'sales_notes.id', '=', 'sales_note_items.sales_note_id')
            ->where('sales_notes.sales_id', $sales->id)
            ->where('sales_notes.line', $line->value)
            ->where('sales_notes.source', 'bawaan') // consigned goods were already counted out when left at the store
            ->groupBy('sales_note_items.product_id')
            ->selectRaw('sales_note_items.product_id as product_id, SUM(sales_note_items.quantity) as qty')
            ->pluck('qty', 'product_id')
            ->map(fn ($q) => (int) $q);
    }

    /** @return Collection<int, int> product_id => pcs this sales person left at stores (titip) */
    private static function leftAtStores(User $sales, BusinessLine $line): Collection
    {
        return DB::table('consignment_moves')->where('sales_id', $sales->id)->where('line', $line->value)->where('kind', 'titip')
            ->groupBy('product_id')->selectRaw('product_id, SUM(quantity) as qty')->pluck('qty', 'product_id')->map(fn ($q) => (int) $q);
    }
}
