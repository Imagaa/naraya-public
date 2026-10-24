<?php

use App\Livewire\Marketplace\Home;
use App\Models\Product;
use App\Support\Cart;
use Livewire\Livewire;

it('adds the minimum purchase quantity when no quantity is given', function () {
    $product = Product::factory()->create(['stock' => 20, 'min_purchase' => 3]);

    Livewire::test(Home::class)->call('addToCart', $product->id);

    expect(Cart::quantities())->toBe([$product->id => 3]);
});

it('sets the cart quantity and removes the line below the minimum', function () {
    $product = Product::factory()->create(['stock' => 20, 'min_purchase' => 2]);
    Cart::add($product->id, 2);

    $component = Livewire::test(Home::class)->call('setCartQuantity', $product->id, 5);
    expect(Cart::quantities())->toBe([$product->id => 5]);

    $component->call('setCartQuantity', $product->id, 1);
    expect(Cart::quantities())->toBe([]);
});

it('caps the cart quantity at stock', function () {
    $product = Product::factory()->create(['stock' => 4]);

    Livewire::test(Home::class)->call('setCartQuantity', $product->id, 9);

    expect(Cart::quantities())->toBe([$product->id => 4]);
});
