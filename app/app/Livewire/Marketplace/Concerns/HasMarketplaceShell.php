<?php

namespace App\Livewire\Marketplace\Concerns;

use App\Models\Product;
use App\Models\StockAlert;
use App\Support\Cart;

trait HasMarketplaceShell
{
    // Logout lives at App\Http\Controllers\Auth\LogoutController (a real
    // route, not a component action) — a wire:click placed in a Blade
    // component included from the layout works, since <x-mp.header>
    // renders inside each page's own component boundary, but a route-based
    // form has no such boundary to get wrong in the first place.

    protected function cartCount(): int
    {
        return Cart::count();
    }

    /** @return array<int, int> product id => quantity in the cart */
    protected function cartQuantities(): array
    {
        return Cart::quantities();
    }

    /** A null quantity means the product's minimum purchase. Cart caps it at stock. */
    public function addToCart(int $productId, ?int $quantity = null): void
    {
        if (! config('naraya.marketplace_purchasing_open')) {
            return; // online buying on hold
        }
        $product = Product::forLine(\App\Support\ActiveLine::current())->find($productId);

        if ($product) {
            Cart::add($productId, $quantity ?? $product->minQuantity());
        }
    }

    /** Going below the minimum purchase removes the line entirely. */
    public function setCartQuantity(int $productId, int $quantity): void
    {
        if (! config('naraya.marketplace_purchasing_open')) {
            return;
        }
        $product = Product::forLine(\App\Support\ActiveLine::current())->find($productId);

        if (! $product) {
            return;
        }

        $quantity < $product->minQuantity()
            ? Cart::remove($productId)
            : Cart::updateQuantity($productId, $quantity);
    }

    /** "Kabari saya": subscribe or unsubscribe; the unique index blocks duplicates. Guests go to login. */
    public function toggleStockAlert(int $productId)
    {
        if (auth()->guest()) {
            return $this->redirectRoute('login');
        }

        $removed = StockAlert::where('user_id', auth()->id())->where('product_id', $productId)->delete();

        if (! $removed && Product::whereKey($productId)->exists()) {
            StockAlert::create(['user_id' => auth()->id(), 'product_id' => $productId]);
        }
    }

    /** @return array<int, int> product ids the logged-in buyer is waiting on */
    protected function stockAlertIds(): array
    {
        return auth()->check()
            ? StockAlert::where('user_id', auth()->id())->pluck('product_id')->all()
            : [];
    }
}
