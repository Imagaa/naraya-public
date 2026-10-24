<?php

use App\Enums\CancelReason;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Exceptions\OrderNotCancellableException;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\OrderCanceller;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->product = Product::factory()->create(['stock' => 10]);
});

function orderWith(array $attrs, Product $product, int $qty = 4): Order
{
    $order = Order::factory()->create($attrs);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 1000, 'quantity' => $qty, 'subtotal' => 1000 * $qty]);

    return $order;
}

it('cancels a New order without touching stock', function () {
    $order = orderWith(['status' => OrderStatus::New], $this->product);

    (new OrderCanceller)->cancel($order, CancelReason::TokoMintaBatal, null, $this->admin);

    expect($order->fresh())
        ->status->toBe(OrderStatus::Cancelled)
        ->cancel_reason->toBe(CancelReason::TokoMintaBatal)
        ->cancelled_by->toBe($this->admin->id)
        ->cancelled_at->not->toBeNull();
    expect($this->product->fresh()->stock)->toBe(10);
});

it('cancels a Processing order before handover and returns its stock', function () {
    $order = orderWith(['status' => OrderStatus::Processing, 'gudang_handover_at' => null], $this->product, 4);

    (new OrderCanceller)->cancel($order, CancelReason::StokHabis, null, $this->admin);

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($this->product->fresh()->stock)->toBe(14);
});

it('refuses once the goods left the warehouse', function (array $attrs) {
    $order = orderWith($attrs, $this->product);

    expect(fn () => (new OrderCanceller)->cancel($order, CancelReason::StokHabis, null, $this->admin))
        ->toThrow(OrderNotCancellableException::class, 'Barang sudah keluar gudang');
    expect($order->fresh()->status)->toBe($attrs['status']);
})->with([
    'handed over' => [['status' => OrderStatus::Processing, 'gudang_handover_at' => now()]],
    'shipped' => [['status' => OrderStatus::Shipped]],
]);

it('refuses a completed order as already closed', function () {
    $order = orderWith(['status' => OrderStatus::Completed], $this->product);

    expect(fn () => (new OrderCanceller)->cancel($order, CancelReason::StokHabis, null, $this->admin))
        ->toThrow(OrderNotCancellableException::class, 'sudah dibatalkan atau ditutup');
});

it('refuses to cancel an order that is already cancelled', function () {
    $order = orderWith(['status' => OrderStatus::Processing], $this->product, 4);
    $canceller = new OrderCanceller;
    $canceller->cancel($order, CancelReason::StokHabis, null, $this->admin);

    expect(fn () => $canceller->cancel($order->fresh(), CancelReason::StokHabis, null, $this->admin))
        ->toThrow(OrderNotCancellableException::class);
    expect($this->product->fresh()->stock)->toBe(14); // returned once, not twice
});

it('requires a note when the reason is Lainnya', function () {
    $order = orderWith(['status' => OrderStatus::New], $this->product);

    expect(fn () => (new OrderCanceller)->cancel($order, CancelReason::Lainnya, '  ', $this->admin))
        ->toThrow(OrderNotCancellableException::class, 'catatan');

    (new OrderCanceller)->cancel($order, CancelReason::Lainnya, 'Toko tutup permanen', $this->admin);
    expect($order->fresh()->cancel_note)->toBe('Toko tutup permanen');
});

it('returns store credit used on the order', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'credit_balance' => 1000]);
    $order = orderWith(['status' => OrderStatus::New, 'buyer_id' => $buyer->id, 'credit_applied' => 5000], $this->product);

    (new OrderCanceller)->cancel($order, CancelReason::StokHabis, null, $this->admin);

    expect($buyer->buyerProfile->fresh()->credit_balance)->toBe(6000);
});

it('flags a refund only for a paid online order', function (string $method, PaymentStatus $payment, ?string $refund) {
    $order = orderWith(['status' => OrderStatus::New, 'payment_method' => $method, 'payment_status' => $payment], $this->product);

    (new OrderCanceller)->cancel($order, CancelReason::PembayaranBermasalah, null, $this->admin);

    expect($order->fresh()->refund_status)->toBe($refund);
})->with([
    'paid qris' => ['qris', PaymentStatus::Paid, 'pending'],
    'unpaid qris' => ['qris', PaymentStatus::Pending, null],
    'cash' => ['cash', PaymentStatus::Paid, null],
]);

it('tells the buyer and the assigned sales why the order was cancelled', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = orderWith(['status' => OrderStatus::Processing, 'sales_id' => $sales->id], $this->product);
    $this->actingAs($this->admin);

    (new OrderCanceller)->cancel($order, CancelReason::StokHabis, null, $this->admin);

    expect($order->buyer->fresh()->notifications->pluck('data.body'))->toContain("Pesanan #{$order->id} dibatalkan: Stok habis");
    expect($sales->fresh()->notifications->pluck('data.body'))->toContain("Pesanan #{$order->id} dibatalkan: Stok habis");
});

it('exposes whether an order can still be cancelled', function () {
    expect(OrderCanceller::canCancel(Order::factory()->make(['status' => OrderStatus::New])))->toBeTrue()
        ->and(OrderCanceller::canCancel(Order::factory()->make(['status' => OrderStatus::Processing, 'gudang_handover_at' => null])))->toBeTrue()
        ->and(OrderCanceller::canCancel(Order::factory()->make(['status' => OrderStatus::Processing, 'gudang_handover_at' => now()])))->toBeFalse()
        ->and(OrderCanceller::canCancel(Order::factory()->make(['status' => OrderStatus::Cancelled])))->toBeFalse();
});
