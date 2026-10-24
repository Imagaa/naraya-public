<?php

namespace App\Livewire\Marketplace\Concerns;

use App\Models\Order;
use App\Support\Cart;

/** "Pesan Lagi" for the orders list and the order detail page. */
trait ReordersOrders
{
    public ?string $flash = null;

    protected function reorderInto(Order $order)
    {
        abort_unless($order->canBeReordered(), 403);

        ['added' => $added, 'skipped' => $skipped] = Cart::addOrder($order);

        if ($added === 0) {
            $this->flash = 'Produk di pesanan ini sedang tidak tersedia.';

            return null;
        }

        session()->flash('status', "{$added} produk ditambahkan ke keranjang.".($skipped ? " {$skipped} produk tidak tersedia." : ''));

        return $this->redirectRoute('checkout');
    }
}
