<?php

use App\Enums\PaymentStatus;
use App\Models\MidtransNotification;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;

// Prefixed distinctly (not signedPayload/pendingOrder) — Pest test files share
// PHP's global function namespace across the whole suite, so generic helper
// names risk a "cannot redeclare" fatal if another test file ever defines one.
function midtransSignedPayload(string $orderId, string $statusCode, string $grossAmount, string $transactionStatus, string $transactionId = 'txn-x'): array
{
    $serverKey = config('services.midtrans.server_key');
    $signature = hash('sha512', $orderId.$statusCode.$grossAmount.$serverKey);

    return [
        'order_id' => $orderId,
        'status_code' => $statusCode,
        'gross_amount' => $grossAmount,
        'transaction_status' => $transactionStatus,
        'transaction_id' => $transactionId,
        'signature_key' => $signature,
    ];
}

function makePendingDigitalOrder(): Order
{
    $buyer = User::factory()->create(['role' => \App\Enums\Role::User]);
    $product = Product::factory()->create(['price' => 15000]);

    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'payment_status' => PaymentStatus::Pending,
        'payment_method' => 'qris',
        'total' => 15000,
    ]);
    $order->update(['midtrans_order_id' => "NR-{$order->id}"]);

    return $order;
}

it('logs every notification, valid or not', function () {
    $order = makePendingDigitalOrder();
    $payload = midtransSignedPayload($order->midtrans_order_id, '200', '15000.00', 'settlement');

    $this->postJson('/midtrans/webhook', $payload)->assertOk();

    expect(MidtransNotification::where('order_id', $order->id)->where('signature_valid', true)->exists())->toBeTrue();
});

it('rejects an unsigned/tampered notification without changing order status', function () {
    $order = makePendingDigitalOrder();
    $payload = midtransSignedPayload($order->midtrans_order_id, '200', '15000.00', 'settlement');
    $payload['signature_key'] = 'tampered';

    $this->postJson('/midtrans/webhook', $payload)->assertStatus(403);

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Pending);
    expect(MidtransNotification::where('order_id', $order->id)->where('signature_valid', false)->exists())->toBeTrue();
});

it('marks the order Paid on a settlement notification', function () {
    $order = makePendingDigitalOrder();
    $payload = midtransSignedPayload($order->midtrans_order_id, '200', '15000.00', 'settlement');

    $this->postJson('/midtrans/webhook', $payload)->assertOk();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('marks the order Failed on an expire notification', function () {
    $order = makePendingDigitalOrder();
    $payload = midtransSignedPayload($order->midtrans_order_id, '200', '15000.00', 'expire');

    $this->postJson('/midtrans/webhook', $payload)->assertOk();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Failed);
});

it('does not let a late pending notification overwrite an already-Paid order', function () {
    $order = makePendingDigitalOrder();
    $order->update(['payment_status' => PaymentStatus::Paid]);

    $payload = midtransSignedPayload($order->midtrans_order_id, '201', '15000.00', 'pending');
    $this->postJson('/midtrans/webhook', $payload)->assertOk();

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
});

it('flags needs_payment_review instead of silently reopening an order already marked Failed', function () {
    $order = makePendingDigitalOrder();
    $order->update(['payment_status' => PaymentStatus::Failed]);

    $payload = midtransSignedPayload($order->midtrans_order_id, '200', '15000.00', 'settlement');
    $this->postJson('/midtrans/webhook', $payload)->assertOk();

    $order->refresh();
    expect($order->payment_status)->toBe(PaymentStatus::Failed); // untouched
    expect($order->needs_payment_review)->toBeTrue();
});
