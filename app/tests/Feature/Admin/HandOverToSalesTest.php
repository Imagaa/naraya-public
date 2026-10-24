<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Show;
use App\Livewire\Sales\Deliveries\Show as Delivery;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

// The admin hands goods to the sales person (the gudang portal is gone)
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales]);
});

it('lets admin hand a processing order to its sales person, who can then receive it', function () {
    Storage::fake('s3');
    $order = Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $this->sales->id, 'sales_assigned_at' => now()]);

    Livewire::actingAs($this->admin)->test(Show::class, ['order' => $order])
        ->assertSee('Serahkan ke sales')->call('handOverToSales')->assertHasNoErrors();

    expect($order->fresh())->gudang_handover_at->not->toBeNull()->gudang_handover_by->toBe($this->admin->id)
        ->and($this->sales->notifications()->where('data->title', 'Barang siap diambil')->exists())->toBeTrue();

    Livewire::actingAs($this->sales)->test(Delivery::class, ['order' => $order->fresh()])
        ->set('receivedPhoto', UploadedFile::fake()->image('terima.jpg'))->call('confirmReceivedFromGudang')->assertHasNoErrors();
    expect($order->fresh()->sales_received_at)->not->toBeNull();
});

it('refuses a handover without a sales person or twice', function () {
    $noSales = Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => null]);
    Livewire::actingAs($this->admin)->test(Show::class, ['order' => $noSales])
        ->assertDontSee('Serahkan ke sales')->call('handOverToSales')->assertForbidden();

    $done = Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $this->sales->id, 'gudang_handover_at' => now()]);
    Livewire::actingAs($this->admin)->test(Show::class, ['order' => $done])->call('handOverToSales')->assertForbidden();
});

it('no longer serves the gudang portal', function () {
    $this->get('http://gudang.'.config('app.domain').'/login')->assertNotFound();
    $this->get('http://'.config('app.staff_domain').'/')->assertDontSee('Siapkan dan serahkan barang');
});
