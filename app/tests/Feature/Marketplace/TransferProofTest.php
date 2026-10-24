<?php

use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Show as AdminOrder;
use App\Livewire\Marketplace\Orders\Show as OrderPage;
use App\Models\Address;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Support\Orders\TransferPayments;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

// BNI transfer proof flow
beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-23 03:00'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $this->buyer->id, 'credit_balance' => 0]);
    Address::factory()->for($this->buyer)->create();
    $product = Product::factory()->create(['price' => 5000, 'stock' => 100]);
    $this->order = function (string $method = 'transfer', int $credit = 0) use ($product) {
        $order = Order::factory()->create([
            'buyer_id' => $this->buyer->id, 'status' => OrderStatus::New, 'payment_method' => $method,
            'payment_status' => $method === 'transfer' ? PaymentStatus::Pending : PaymentStatus::Paid,
            'payment_expires_at' => $method === 'transfer' ? now()->addMinutes(30) : null,
            'subtotal' => 10000 + $credit, 'credit_applied' => $credit, 'total' => 10000,
        ]);
        $order->items()->create(['product_id' => $product->id, 'product_name' => $product->name, 'price' => 5000, 'quantity' => 2, 'subtotal' => 10000]);

        return $order;
    };
    $this->payments = app(TransferPayments::class);
});

it('walks a transfer from proof to verified', function () {
    $order = ($this->order)();
    $page = Livewire::actingAs($this->buyer)->test(OrderPage::class, ['order' => $order])
        ->assertSee('Menunggu bukti bayar')->assertSee(config('naraya.transfer_to'))->assertSee('Rp 10.000');

    $page->set('proof', UploadedFile::fake()->image('bukti.jpg'))->call('uploadProof')->assertHasNoErrors()
        ->assertSee('Menunggu verifikasi');
    expect($order->fresh())->payment_proof_path->not->toBeNull()->payment_expires_at->toBeNull()
        ->and($this->admin->notifications()->where('data->title', 'Bukti transfer pesanan #'.$order->id)->exists())->toBeTrue();

    Livewire::actingAs($this->admin)->test(AdminOrder::class, ['order' => $order])->assertSee('Bukti transfer')->call('verifyTransfer');

    expect($order->fresh()->payment_status)->toBe(PaymentStatus::Paid);
    Livewire::actingAs($this->buyer)->test(OrderPage::class, ['order' => $order->fresh()])
        ->assertSee('Pembayaran terverifikasi')
        ->assertSee('Terima kasih! Pesanan Anda kami kirim sebagai pesanan prioritas.');
});

it('restarts the 30 minutes when admin rejects', function () {
    $order = ($this->order)();
    $this->payments->upload($order, UploadedFile::fake()->image('bukti.jpg'));
    $this->travelTo(Carbon::parse('2026-10-23 03:20'));

    Livewire::actingAs($this->admin)->test(AdminOrder::class, ['order' => $order->fresh()])
        ->call('rejectTransfer')->assertHasErrors('rejectReason')
        ->set('rejectReason', 'Nominal kurang')->call('rejectTransfer')->assertHasNoErrors();

    expect($order->fresh())->payment_status->toBe(PaymentStatus::Pending)->payment_proof_path->toBeNull()
        ->and($order->fresh()->payment_expires_at->toDateTimeString())->toBe('2026-10-23 03:50:00');
    Livewire::actingAs($this->buyer)->test(OrderPage::class, ['order' => $order->fresh()])
        ->assertSee('Menunggu bukti bayar')->assertSee('Nominal kurang')
        ->set('proof', UploadedFile::fake()->image('bukti2.jpg'))->call('uploadProof')->assertHasNoErrors();
});

it('cancels transfer orders without proof after 30 minutes', function () {
    $this->buyer->buyerProfile->update(['credit_balance' => 0]);
    $order = ($this->order)('transfer', 2000);
    $this->travelTo(Carbon::parse('2026-10-23 03:31'));

    $this->artisan('orders:expire-transfers')->assertSuccessful();

    expect($order->fresh())->status->toBe(OrderStatus::Cancelled)->cancelled_by->toBeNull()
        ->and($order->fresh()->cancel_note)->toBe('Bukti transfer tidak diunggah dalam 30 menit')
        ->and($this->buyer->buyerProfile->fresh()->credit_balance)->toBe(2000);
});

it('never cancels an order that has a proof', function () {
    $order = ($this->order)();
    $this->payments->upload($order, UploadedFile::fake()->image('bukti.jpg'));
    $order->forceFill(['payment_expires_at' => now()->subMinute()])->save(); // as if the job raced the upload

    $this->travelTo(Carbon::parse('2026-10-23 04:00'));
    $this->artisan('orders:expire-transfers')->assertSuccessful();

    expect($order->fresh()->status)->toBe(OrderStatus::New);
});

it('refuses to reject a cancelled order', function () {
    $order = ($this->order)();
    $this->travelTo(Carbon::parse('2026-10-23 03:31'));
    $this->artisan('orders:expire-transfers');

    expect(fn () => $this->payments->reject($order->fresh(), $this->admin, 'Nominal kurang'))->toThrow(ValidationException::class)
        ->and(fn () => $this->payments->upload($order->fresh(), UploadedFile::fake()->image('b.jpg')))->toThrow(ValidationException::class);
});

it('shows the cash thank-you with the amount to prepare', function () {
    Livewire::actingAs($this->buyer)->test(OrderPage::class, ['order' => ($this->order)('cash')])
        ->assertSee('Terima kasih! Pesanan Anda kami kirim sebagai pesanan prioritas.')
        ->assertSee('Siapkan uang Rp 10.000')
        ->assertDontSee('Menunggu bukti bayar');
});

it('blocks admin confirm until the transfer is verified', function () {
    $order = ($this->order)();

    Livewire::actingAs($this->admin)->test(AdminOrder::class, ['order' => $order])->call('confirm');
    expect($order->fresh()->status)->toBe(OrderStatus::New);

    $this->payments->upload($order, UploadedFile::fake()->image('bukti.jpg'));
    $this->payments->verify($order->fresh(), $this->admin);
    Livewire::actingAs($this->admin)->test(AdminOrder::class, ['order' => $order->fresh()])->call('confirm');
    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});
