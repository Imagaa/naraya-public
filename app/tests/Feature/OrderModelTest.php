<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

it('creates an order with items and computes relations correctly', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    $product = Product::factory()->create(['price' => 5000]);

    $order = Order::create([
        'buyer_id' => $buyer->id,
        'status' => OrderStatus::New,
        'payment_method' => 'cash',
        'subtotal' => 10000,
        'total' => 10000,
        'delivery_address' => 'Jl. Contoh No. 1',
    ]);

    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => 2,
        'subtotal' => 10000,
    ]);

    expect($order->buyer->is($buyer))->toBeTrue();
    expect($order->items)->toHaveCount(1);
    expect($order->items->first()->product->is($product))->toBeTrue();
    expect($order->status)->toBe(OrderStatus::New);
});

it('keeps the order item price snapshot even if the product price changes later', function () {
    $product = Product::factory()->create(['price' => 5000]);
    $order = Order::factory()->create();
    $item = $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => 1,
        'subtotal' => 5000,
    ]);

    $product->update(['price' => 9999]);

    expect($item->fresh()->price)->toBe(5000);
});

it('derives the sales delivery stage from the handover and confirmation timestamps', function () {
    $awaitingHandover = Order::factory()->create(['status' => OrderStatus::Processing]);
    expect($awaitingHandover->salesStage())->toBe('awaiting_handover');

    $readyToReceive = Order::factory()->create(['status' => OrderStatus::Processing, 'gudang_handover_at' => now()]);
    expect($readyToReceive->salesStage())->toBe('ready_to_receive');

    $readyToShip = Order::factory()->create([
        'status' => OrderStatus::Processing,
        'gudang_handover_at' => now(),
        'sales_received_at' => now(),
    ]);
    expect($readyToShip->salesStage())->toBe('ready_to_ship');

    $inTransit = Order::factory()->create(['status' => OrderStatus::Shipped]);
    expect($inTransit->salesStage())->toBe('in_transit');

    $awaitingBuyerConfirm = Order::factory()->create(['status' => OrderStatus::Shipped, 'sales_delivered_at' => now()]);
    expect($awaitingBuyerConfirm->salesStage())->toBe('awaiting_buyer_confirm');
});

it('counts only orders still needing sales action, excluding ones awaiting buyer confirmation', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);

    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Processing]);
    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => null]);
    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => now()]);
    Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Completed]);
    Order::factory()->create(['sales_id' => $otherSales->id, 'status' => OrderStatus::Processing]);

    expect(Order::pendingSalesActionCount($sales->id))->toBe(2);
});
