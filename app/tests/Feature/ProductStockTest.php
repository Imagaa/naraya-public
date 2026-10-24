<?php

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\User;

it('increases stock and records a stock movement', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $product = Product::factory()->create(['stock' => 10]);

    $product->adjustStock(20, StockMovementType::In, $admin, 'Restock');

    expect($product->fresh()->stock)->toBe(30);
    expect($product->stockMovements()->count())->toBe(1);
    expect($product->stockMovements()->first()->type)->toBe(StockMovementType::In);
});

it('decreases stock correctly', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $product = Product::factory()->create(['stock' => 10]);

    $product->adjustStock(-4, StockMovementType::Out, $admin, 'Order #1');

    expect($product->fresh()->stock)->toBe(6);
});

it('never allows stock to go negative', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $product = Product::factory()->create(['stock' => 5]);

    expect(fn () => $product->adjustStock(-10, StockMovementType::Out, $admin))
        ->toThrow(InsufficientStockException::class);

    // Stock and movement history are untouched — the failed attempt is not partially applied.
    expect($product->fresh()->stock)->toBe(5);
    expect($product->stockMovements()->count())->toBe(0);
});
