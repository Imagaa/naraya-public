<?php

use App\Enums\PaymentStatus;
use App\Models\Order;

it('defaults new orders to Paid (cash simplification already in place)', function () {
    $order = Order::factory()->create();

    expect($order->payment_status)->toBe(PaymentStatus::Paid);
});

it('casts payment_status to the enum and stores midtrans fields', function () {
    $order = Order::factory()->create([
        'payment_status' => PaymentStatus::Pending,
        'midtrans_order_id' => 'NR-1',
        'midtrans_transaction_id' => 'abc-123',
        'midtrans_payment_type' => 'qris',
        'payment_expires_at' => now()->addMinutes(30),
    ]);

    expect($order->payment_status)->toBe(PaymentStatus::Pending);
    expect($order->midtrans_order_id)->toBe('NR-1');
});
