<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\StockMovement;

it('creates an order item snapshotting the product name and price', function () {
    $product = Product::factory()->create(['name' => 'Indomie Goreng', 'price' => 3500]);
    $order = Order::factory()->create();

    $item = OrderItem::factory()->create([
        'order_id' => $order->id,
        'product_id' => $product->id,
    ]);

    expect($item->product_name)->not->toBeEmpty();
    expect($item->price)->toBeGreaterThan(0);
    expect($item->subtotal)->toBe($item->price * $item->quantity);
});

it('creates a stock movement', function () {
    $movement = StockMovement::factory()->create();

    expect($movement->quantity)->not->toBeNull();
    expect($movement->product)->toBeInstanceOf(Product::class);
    expect($movement->user)->not->toBeNull();
});
