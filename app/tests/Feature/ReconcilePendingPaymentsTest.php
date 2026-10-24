<?php

use App\Enums\PaymentStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Http;

it('marks an expired-and-since-settled order as Paid after checking Midtrans directly', function () {
    $buyer = User::factory()->create(['role' => \App\Enums\Role::User]);
    Product::factory()->create();
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'qris',
        'payment_expires_at' => now()->subMinutes(5),
    ]);
    $order->update(['midtrans_order_id' => "NR-{$order->id}"]);

    Http::fake(["*/v2/NR-{$order->id}/status" => Http::response(['transaction_status' => 'settlement'], 200)]);

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('ignores orders whose expiry has not passed yet', function () {
    $buyer = User::factory()->create(['role' => \App\Enums\Role::User]);
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'payment_status' => PaymentStatus::Pending,
        'payment_expires_at' => now()->addMinutes(10),
    ]);

    Http::fake(); // if reconcile touches this order it'll hit an unfaked URL and fail the test

    $this->artisan('payments:reconcile')->assertSuccessful();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
});
