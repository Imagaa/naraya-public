<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\User;

it('auto-completes a Shipped order the buyer never confirmed within 3 days of delivery', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 0]);
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped, 'total' => 15000,
        'sales_delivered_at' => now()->subDays(4), 'buyer_confirmed_at' => null,
    ]);

    $this->artisan('orders:auto-complete-deliveries');

    $order->refresh();
    expect($order->status)->toBe(OrderStatus::Completed);
    expect($order->buyer_confirmed_at)->not->toBeNull();
    expect($order->completed_at)->not->toBeNull();
    expect($buyer->buyerProfile->fresh()->loyalty_points)->toBe(0); // orders no longer earn points
});

it('does not touch a Shipped order still within its 3-day confirmation window', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Shipped, 'sales_delivered_at' => now()->subDays(1), 'buyer_confirmed_at' => null,
    ]);

    $this->artisan('orders:auto-complete-deliveries');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('does not touch an order the buyer already confirmed', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Shipped, 'sales_delivered_at' => now()->subDays(5), 'buyer_confirmed_at' => now(),
    ]);

    $this->artisan('orders:auto-complete-deliveries');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('does not touch an order Sales has not delivered yet', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::Shipped, 'sales_delivered_at' => null, 'buyer_confirmed_at' => null,
    ]);

    $this->artisan('orders:auto-complete-deliveries');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});
