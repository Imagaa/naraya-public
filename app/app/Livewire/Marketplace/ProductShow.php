<?php

namespace App\Livewire\Marketplace;

use App\Livewire\Marketplace\Concerns\HasMarketplaceShell;
use App\Models\Product;
use App\Support\Catalog;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class ProductShow extends Component
{
    use HasMarketplaceShell;

    public Product $product;

    public int $quantity = 1;

    public bool $added = false;

    public function mount(Product $product): void
    {
        // the marketplace sells the Naraya line only
        abort_unless($product->is_active, 404);
        abort_unless(\App\Support\ActiveLine::isOpen($product->line), 404);
        \App\Support\ActiveLine::set($product->line); // a shared link opens the product's own storefront

        $this->product = $product->load(['category', 'photos']);
        $this->quantity = $product->minQuantity();
    }

    public function increment(): void
    {
        $this->quantity = min($this->quantity + 1, max($this->product->unitsInStock(), $this->product->minQuantity()));
        $this->added = false;
    }

    public function decrement(): void
    {
        $this->quantity = max($this->quantity - 1, $this->product->minQuantity());
        $this->added = false;
    }

    public function addSelectedToCart()
    {
        if (auth()->guest()) {
            return $this->redirectRoute('login');
        }

        $this->addToCart($this->product->id, $this->quantity);
        $this->added = true;
    }

    public function buyNow()
    {
        if (auth()->guest()) {
            return $this->redirectRoute('login');
        }

        $this->addToCart($this->product->id, $this->quantity);

        return $this->redirectRoute('checkout');
    }

    public function render()
    {
        $subtotal = $this->product->effectivePrice() * $this->quantity;

        return view('livewire.marketplace.product-show', [
            'unit' => $this->product->unit ?: 'pcs',
            'available' => $this->product->isAvailable(),
            'subtotal' => $subtotal,
            'gallery' => collect($this->product->galleryPaths())->map(fn ($path) => Storage::disk('s3')->url($path)),
            'related' => $related = Catalog::boughtWith($this->product, 10),
            'similar' => Catalog::similarTo($this->product, $related->pluck('id')->all()),
            'salesName' => auth()->user()?->buyerProfile?->salesUser?->name,
            'cartQty' => $this->cartQuantities(),
            'alertIds' => $this->stockAlertIds(),
        ])->layout('layouts.marketplace')->title($this->product->name.' — Naraya');
    }
}
