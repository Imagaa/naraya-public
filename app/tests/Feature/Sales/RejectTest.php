<?php

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Sales\Deliveries\Reject;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');
});

it('blocks a sales user from acting on an order assigned to someone else', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $otherSales->id, 'status' => OrderStatus::Shipped]);

    $this->actingAs($sales)
        ->get('http://sales.'.config('app.domain')."/deliveries/{$order->id}/tolak")
        ->assertStatus(403);
});

it('blocks opening the reject page for a delivery Sales already confirmed as delivered', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'sales_delivered_at' => now(),
    ]);

    $this->actingAs($sales)
        ->get('http://sales.'.config('app.domain')."/deliveries/{$order->id}/tolak")
        ->assertStatus(403);
});

it('records a rejection with a reason and photo when the buyer refuses the delivery', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped]);

    Livewire::actingAs($sales)
        ->test(Reject::class, ['order' => $order])
        ->set('rejectionReason', 'Barang rusak saat diterima')
        ->set('rejectionPhoto', UploadedFile::fake()->image('tolak.jpg'))
        ->call('rejectDelivery')
        ->assertRedirect(route('sales.orders.index'));

    expect($order->fresh()->status)->toBe(OrderStatus::RejectedByUser);

    $complaint = Complaint::where('order_id', $order->id)->firstOrFail();
    expect($complaint->tipe)->toBe(ComplaintType::TolakSaatTerima);
    expect($complaint->status)->toBe(ComplaintStatus::Diajukan);
    expect($complaint->alasan)->toBe('Barang rusak saat diterima');
    expect($complaint->foto_path)->not->toBeNull();
});

it('requires a photo when recording a rejection', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped]);

    Livewire::actingAs($sales)
        ->test(Reject::class, ['order' => $order])
        ->set('rejectionReason', 'Barang rusak saat diterima')
        ->call('rejectDelivery')
        ->assertHasErrors('rejectionPhoto');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('requires a reason when recording a rejection', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped]);

    Livewire::actingAs($sales)
        ->test(Reject::class, ['order' => $order])
        ->set('rejectionPhoto', UploadedFile::fake()->image('tolak.jpg'))
        ->call('rejectDelivery')
        ->assertHasErrors('rejectionReason');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});
