<?php

use App\Enums\CancelReason;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Show;
use App\Models\BuyerProfile;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

function detail(Order $order)
{
    return Livewire::actingAs(test()->admin)->test(Show::class, ['order' => $order]);
}

it('puts the one next step for each state at the top', function (array $attrs, string $expected) {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(array_map(fn ($v) => $v === 'SALES' ? $sales->id : $v, $attrs));

    detail($order)->assertSeeInOrder(['Langkah berikutnya', $expected]);
})->with([
    'new' => [['status' => OrderStatus::New], 'Konfirmasi & Proses'],
    'processing no sales' => [['status' => OrderStatus::Processing, 'sales_id' => null], 'Tandai dikirim (diantar admin)'],
    'hand over to sales' => [['status' => OrderStatus::Processing, 'sales_id' => 'SALES', 'sales_assigned_at' => now()->subHours(3)], 'Serahkan ke sales'],
    'sales received' => [['status' => OrderStatus::Processing, 'sales_id' => 'SALES', 'gudang_handover_at' => now(), 'sales_received_at' => now()], 'Tandai dikirim'],
    'shipped no sales' => [['status' => OrderStatus::Shipped, 'sales_id' => null], 'Tandai selesai'],
    'awaiting close' => [['status' => OrderStatus::Shipped, 'sales_id' => 'SALES', 'sales_delivered_at' => now(), 'buyer_confirmed_at' => now()], 'Tutup pesanan'],
]);

it('offers the rejected-goods verification for a rejected order', function () {
    $order = Order::factory()->create(['status' => OrderStatus::RejectedByUser]);
    Complaint::factory()->create(['order_id' => $order->id, 'tipe' => ComplaintType::TolakSaatTerima, 'status' => ComplaintStatus::Diajukan]);

    detail($order)->assertSee('Kondisi baik')->assertSee('Kondisi rusak');
});

it('confirms with the sales picked in the next-step box', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['status' => OrderStatus::New]);
    $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 1000, 'quantity' => 1, 'subtotal' => 1000]);

    detail($order)->set('salesUserId', $sales->id)->call('confirm');

    expect($order->fresh())->status->toBe(OrderStatus::Processing)->sales_id->toBe($sales->id);
});

it('shows Batalkan only while the order can still be cancelled', function () {
    detail(Order::factory()->create(['status' => OrderStatus::New]))->assertSee('Batalkan pesanan #');
    detail(Order::factory()->create(['status' => OrderStatus::Processing, 'gudang_handover_at' => now()]))->assertDontSee('Batalkan pesanan #');
});

it('spells out what cancelling will do', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Processing, 'credit_applied' => 50000, 'payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid]);
    $order->items()->create(['product_id' => Product::factory()->create()->id, 'product_name' => 'Gula', 'price' => 1000, 'quantity' => 2, 'subtotal' => 2000]);

    detail($order)
        ->assertSee('Stok 1 barang dikembalikan ke gudang.')
        ->assertSee('Kredit Rp 50.000 dikembalikan ke saldo toko.')
        ->assertSee('Pembayaran QRIS sudah masuk — perlu refund manual.');
});

it('cancels from the dialog and reports a refusal inline', function () {
    $order = Order::factory()->create(['status' => OrderStatus::New]);

    detail($order)->call('cancel')->assertHasErrors('cancelReason');

    detail($order)->set('cancelReason', CancelReason::Lainnya->value)->call('cancel')->assertHasErrors('cancel');

    detail($order)->set('cancelReason', CancelReason::TokoMintaBatal->value)->call('cancel')->assertHasNoErrors()->assertDispatched('order-cancelled');
    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
});

it('keeps the cancel dialog markup re-render safe', function () {
    detail(Order::factory()->create(['status' => OrderStatus::New]))->assertSeeHtml('<dialog id="cancel-dialog"')->assertSeeHtml('wire:ignore.self');
});

it('shows the refund alert with its action', function () {
    detail(Order::factory()->create(['status' => OrderStatus::Cancelled, 'refund_status' => 'pending', 'payment_method' => 'qris']))
        ->assertSee('Perlu refund manual')->assertSee('Tandai refund selesai');
});

it('links the store on WhatsApp, phone and map', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'phone' => '6281234567890', 'latitude' => -6.2, 'longitude' => 106.8]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'delivery_lat' => null, 'delivery_lng' => null]);

    detail($order)
        ->assertSeeHtml('href="https://wa.me/6281234567890"')
        ->assertSeeHtml('href="tel:+6281234567890"')
        ->assertSeeHtml('href="https://www.google.com/maps?q=-6.2,106.8"');
});

it('hides the map link when there are no coordinates', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'latitude' => null, 'longitude' => null]);

    detail(Order::factory()->create(['buyer_id' => $buyer->id, 'delivery_lat' => null, 'delivery_lng' => null]))->assertDontSee('Buka di peta');
});

it('always shows the order flow, ending with the cancellation', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Cancelled, 'cancel_reason' => CancelReason::StokHabis, 'cancelled_at' => now(), 'cancelled_by' => $this->admin->id]);

    detail($order)->assertSee('Alur pesanan')->assertSeeInOrder(['Dipesan', 'Dibatalkan'])->assertSee('Stok habis');
});
