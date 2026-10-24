<?php

namespace App\Support;

use App\Models\Order;
use App\Models\Product;
use Illuminate\Support\Collection;

class Cart
{
    protected const SESSION_KEY = 'cart';

    /**
     * Caps the resulting quantity at the product's current stock — the
     * client already limits the qty stepper to stock, this is the
     * server-side backstop (stock can change between page load and
     * click, and a bypassed client is never trusted for a write like this).
     */
    public static function add(int $productId, int $quantity = 1): void
    {
        $product = Product::forLine(\App\Support\ActiveLine::current())->find($productId);

        if (! $product) {
            return;
        }

        $items = session(self::SESSION_KEY, []);
        $requested = ($items[$productId] ?? 0) + $quantity;
        $capped = max(0, min($requested, $product->unitsInStock()));

        if ($capped === 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $capped;
        }

        session([self::SESSION_KEY => $items]);
    }

    /**
     * "Pesan Lagi": put an old order's items back in the cart. Inactive and
     * unavailable products are skipped; quantities rise to the product's
     * minimum and add() caps them at stock.
     *
     * @return array{added: int, skipped: int}
     */
    public static function addOrder(Order $order): array
    {
        $order->loadMissing('items.product');
        $added = 0;

        foreach ($order->items as $item) {
            $product = $item->product;

            if (! $product || ! $product->is_active || ! $product->isAvailable()) {
                continue;
            }

            self::add($product->id, max($item->quantity, $product->minQuantity()));
            $added++;
        }

        return ['added' => $added, 'skipped' => $order->items->count() - $added];
    }

    public static function updateQuantity(int $productId, int $quantity): void
    {
        $product = Product::forLine(\App\Support\ActiveLine::current())->find($productId);
        $items = session(self::SESSION_KEY, []);
        $capped = $product ? min($quantity, $product->unitsInStock()) : $quantity;

        if ($capped <= 0) {
            unset($items[$productId]);
        } else {
            $items[$productId] = $capped;
        }

        session([self::SESSION_KEY => $items]);
    }

    public static function remove(int $productId): void
    {
        $items = session(self::SESSION_KEY, []);
        unset($items[$productId]);
        session([self::SESSION_KEY => $items]);
    }

    public static function items(): Collection
    {
        $raw = session(self::SESSION_KEY, []);

        if (empty($raw)) {
            return collect();
        }

        return Product::forLine(\App\Support\ActiveLine::current())->whereIn('id', array_keys($raw))->get()->map(function (Product $product) use ($raw) {
            $unitPrice = $product->hasDiscount() ? $product->discount_price : $product->price;

            return [
                'product' => $product,
                'quantity' => $raw[$product->id],
                'unitPrice' => $unitPrice,
                'subtotal' => $unitPrice * $raw[$product->id],
            ];
        });
    }

    public static function count(): int
    {
        return array_sum(session(self::SESSION_KEY, []));
    }

    /** @return array<int, int> product id => quantity currently in the cart */
    public static function quantities(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public static function total(): int
    {
        return (int) self::items()->sum('subtotal');
    }

    public static function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
