<?php

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Support\Cart;
use App\Support\Catalog;

function orderIn(string $status, array $attrs = []): Order
{
    return Order::factory()->create(['status' => $status] + $attrs);
}

it('describes each order state for the buyer', function (string $status, array $attrs, int $step, string $tone, string $noteStart) {
    $p = orderIn($status, $attrs)->buyerProgress();

    expect($p['step'])->toBe($step)
        ->and($p['tone'])->toBe($tone)
        ->and($p['note'])->toStartWith($noteStart);
})->with([
    'new' => ['new', [], 1, 'neutral', 'Menunggu konfirmasi admin'],
    'processing' => ['processing', [], 2, 'brand', 'Disiapkan'],
    'on the way' => ['shipped', [], 3, 'info', 'Sedang diantar'],
    'delivered' => ['shipped', ['sales_delivered_at' => now()], 3, 'info', 'Sudah diantar. Konfirmasi penerimaan dalam 3 hari.'],
    'confirmed' => ['shipped', ['sales_delivered_at' => now(), 'buyer_confirmed_at' => now()], 3, 'info', 'Anda sudah konfirmasi'],
    'completed' => ['completed', ['completed_at' => now()], 4, 'ok', 'Selesai'],
    'cancelled' => ['cancelled', [], 0, 'sale', 'Pesanan tidak diproses'],
    'rejected' => ['rejected_by_user', [], 0, 'sale', 'Pesanan tidak diproses'],
]);

it('adds the estimated arrival to a processing order note', function () {
    $p = orderIn('processing', ['estimated_delivery_at' => '2026-10-02 03:00:00'])->buyerProgress();

    expect($p['note'])->toBe('Disiapkan · estimasi tiba 2 Okt');
});

it('lets a buyer confirm a delivered order once', function () {
    $order = orderIn('shipped', ['sales_delivered_at' => now()]);

    expect($order->canBeConfirmedByBuyer())->toBeTrue();

    $order->confirmReceivedByBuyer();

    expect($order->fresh()->buyer_confirmed_at)->not->toBeNull()
        ->and($order->fresh()->canBeConfirmedByBuyer())->toBeFalse();
});

it('refuses to confirm an order that is not waiting for the buyer', function (string $status, array $attrs) {
    orderIn($status, $attrs)->confirmReceivedByBuyer();
})->with([
    'not shipped' => ['processing', []],
    'not delivered yet' => ['shipped', []],
    'already confirmed' => ['shipped', ['sales_delivered_at' => now(), 'buyer_confirmed_at' => now()]],
])->throws(\Symfony\Component\HttpKernel\Exception\HttpException::class);

it('only lets finished orders be reordered', function (string $status, bool $allowed) {
    expect(orderIn($status)->canBeReordered())->toBe($allowed);
})->with([
    ['completed', true], ['cancelled', true], ['rejected_by_user', true],
    ['new', false], ['processing', false], ['shipped', false],
]);

it('re-adds an old order to the cart, skipping what cannot be bought', function () {
    $ok = Product::factory()->create(['stock' => 50, 'min_purchase' => 2, 'is_active' => true]);
    $capped = Product::factory()->create(['stock' => 3, 'min_purchase' => 1, 'is_active' => true]);
    $inactive = Product::factory()->create(['is_active' => false, 'stock' => 10]);
    $empty = Product::factory()->create(['stock' => 0, 'is_active' => true]);
    $order = orderIn('completed');
    OrderItem::factory()->for($order)->create(['product_id' => $ok->id, 'quantity' => 1]);
    OrderItem::factory()->for($order)->create(['product_id' => $capped->id, 'quantity' => 9]);
    OrderItem::factory()->for($order)->create(['product_id' => $inactive->id, 'quantity' => 1]);
    OrderItem::factory()->for($order)->create(['product_id' => $empty->id, 'quantity' => 1]);

    $result = Cart::addOrder($order);

    expect($result)->toBe(['added' => 2, 'skipped' => 2])
        ->and(Cart::quantities())->toBe([$ok->id => 2, $capped->id => 3]);
});

it('recommends products bought with anything in the cart, excluding the cart', function () {
    [$a, $b, $c, $d] = Product::factory()->count(4)->create(['is_active' => true]);
    $o1 = orderIn('completed');
    OrderItem::factory()->for($o1)->create(['product_id' => $a->id]);
    OrderItem::factory()->for($o1)->create(['product_id' => $c->id]);
    $o2 = orderIn('completed');
    OrderItem::factory()->for($o2)->create(['product_id' => $b->id]);
    OrderItem::factory()->for($o2)->create(['product_id' => $c->id]);
    OrderItem::factory()->for($o2)->create(['product_id' => $d->id]);

    $ids = Catalog::boughtWithAny([$a->id, $b->id], null, 6)->pluck('id');

    expect($ids->first())->toBe($c->id)
        ->and($ids)->toContain($d->id)
        ->and($ids)->not->toContain($a->id)
        ->and($ids)->not->toContain($b->id);
});
