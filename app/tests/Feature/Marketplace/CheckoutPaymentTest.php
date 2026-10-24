<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Show as AdminOrder;
use App\Livewire\Marketplace\Checkout;
use App\Models\Address;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\BusinessDay;
use App\Support\Cart;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// Checkout payment + delivery estimate
beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => Role::User]);
    Address::factory()->for($this->buyer)->create();
    $this->product = Product::factory()->create(['price' => 5000, 'stock' => 100, 'is_active' => true]);
    $this->place = function (string $method = 'cash', string $at = '2026-10-23 03:00') {
        $this->travelTo(Carbon::parse($at));
        Cart::add($this->product->id, 2);
        $test = Livewire::actingAs($this->buyer)->test(Checkout::class)->set('paymentMethod', $method)->call('placeOrder');

        return [$test, Order::where('buyer_id', $this->buyer->id)->latest('id')->first()];
    };
    $this->wib = fn (?Carbon $c) => $c?->copy()->timezone(BusinessDay::zone())->format('Y-m-d H:i');
});

it('offers only cash and transfer', function () {
    $this->travelTo(Carbon::parse('2026-10-23 03:00'));
    Cart::add($this->product->id, 1);
    Livewire::actingAs($this->buyer)->test(Checkout::class)
        ->set('step', 'payment')
        ->assertSee('Transfer BNI')->assertDontSee('GoPay')->assertDontSee('QRIS')
        ->set('paymentMethod', 'qris')->call('placeOrder')->assertHasErrors('paymentMethod');

    expect(Order::count())->toBe(0);
});

it('sets tomorrow before 21.00 WIB and nothing after', function () {
    [, $early] = ($this->place)('cash', '2026-10-23 13:59'); // 20.59 WIB
    [, $late] = ($this->place)('cash', '2026-10-23 14:00');  // 21.00 WIB

    expect(($this->wib)($early->estimated_delivery_at))->toBe('2026-10-24 12:00')
        ->and($late->estimated_delivery_at)->toBeNull();
});

it('starts a 30 minute window for transfer orders', function () {
    [$test, $order] = ($this->place)('transfer');

    $test->assertRedirect(route('orders.show', $order));
    expect($order->payment_method)->toBe('transfer')
        ->and($order->payment_status)->toBe(PaymentStatus::Pending)
        ->and($order->payment_expires_at->toDateTimeString())->toBe('2026-10-23 03:30:00')
        ->and($order->status)->toBe(OrderStatus::New);
});

it('keeps the checkout price after a price change', function () {
    [, $order] = ($this->place)();
    $this->product->update(['price' => 9000]);

    expect($order->items()->sole()->price)->toBe(5000)->and($order->fresh()->total)->toBe(10000);
});

it('lets admin confirm without typing an estimate and keeps the system estimate', function () {
    [, $order] = ($this->place)('cash', '2026-10-23 03:00');
    $admin = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($admin)->test(AdminOrder::class, ['order' => $order])->call('confirm')->assertHasNoErrors();

    expect($order->fresh()->status)->toBe(OrderStatus::Processing)
        ->and(($this->wib)($order->fresh()->estimated_delivery_at))->toBe('2026-10-24 12:00');
});
