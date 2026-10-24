<?php

use App\Models\Product;
use App\Support\Cart;

it('adds, updates, and removes items, computing totals from live product prices', function () {
    $product = Product::factory()->create(['price' => 5000, 'stock' => 10]);

    Cart::add($product->id, 2);
    expect(Cart::count())->toBe(2);
    expect(Cart::total())->toBe(10000);

    Cart::updateQuantity($product->id, 5);
    expect(Cart::total())->toBe(25000);

    Cart::remove($product->id);
    expect(Cart::items())->toBeEmpty();
});

it('clears all items', function () {
    $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);
    Cart::add($product->id, 3);

    Cart::clear();

    expect(Cart::items())->toBeEmpty();
    expect(Cart::count())->toBe(0);
});

it('accumulates quantity when the same product is added twice', function () {
    $product = Product::factory()->create(['price' => 1000, 'stock' => 10]);

    Cart::add($product->id, 1);
    Cart::add($product->id, 1);

    expect(Cart::count())->toBe(2);
});
