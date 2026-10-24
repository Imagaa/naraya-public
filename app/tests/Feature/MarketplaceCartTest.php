<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Home;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Livewire\Livewire;

it('lists real products from the database', function () {
    Product::factory()->create(['name' => 'Mie Instan Original', 'is_active' => true]);
    Product::factory()->create(['name' => 'Produk Nonaktif', 'is_active' => false]);

    Livewire::test(Home::class)
        ->assertSee('Mie Instan Original')
        ->assertDontSee('Produk Nonaktif');
});

it('adds a product to the cart from the home page', function () {
    $product = Product::factory()->create(['stock' => 10]);

    Livewire::test(Home::class)->call('addToCart', $product->id);

    expect(Cart::count())->toBe(1);
});

it('adds the requested quantity to the cart, not just one unit', function () {
    $product = Product::factory()->create(['stock' => 10]);

    Livewire::test(Home::class)->call('addToCart', $product->id, 4);

    expect(Cart::count())->toBe(4);
});

it('caps the quantity added to the cart at the available stock', function () {
    $product = Product::factory()->create(['stock' => 3]);

    Livewire::test(Home::class)->call('addToCart', $product->id, 10);

    expect(Cart::count())->toBe(3);
});

it('does not add a sold-out product to the cart', function () {
    $product = Product::factory()->create(['stock' => 0]);

    Livewire::test(Home::class)->call('addToCart', $product->id, 1);

    expect(Cart::count())->toBe(0);
});

it('never renders the entire catalog on the homepage (rails plus 20 explore cards, then Muat lagi)', function () {
    Product::factory()->count(80)->create(['is_active' => true]);

    $home = Livewire::test(Home::class);
    preg_match_all('/data-product-id="(\d+)"/', $home->html(), $matches);

    expect(count(array_unique($matches[1])))->toBeLessThan(80)
        ->and($home->viewData('explore'))->toHaveCount(20);
    $home->assertSee('Muat lagi');
});

