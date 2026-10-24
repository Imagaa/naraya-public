<?php

use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Index;
use App\Livewire\Admin\Orders\Show;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\Complaint;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists orders', function () {
    $order = Order::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('#'.$order->id);
});

it('filters the order list by status', function () {
    $newOrder = Order::factory()->create(['status' => OrderStatus::New]);
    $completedOrder = Order::factory()->create(['status' => OrderStatus::Completed]);

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('statusFilter', 'new')
        ->assertSee('#'.$newOrder->id)
        ->assertDontSee('#'.$completedOrder->id);
});

it('shows the oldest new order first when filtering to Baru', function () {
    $older = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()->subDay()]);
    $newer = Order::factory()->create(['status' => OrderStatus::New, 'created_at' => now()]);

    $html = Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->set('statusFilter', 'new')
        ->html();

    expect(strpos($html, '#'.$older->id))->toBeLessThan(strpos($html, '#'.$newer->id));
});

it('confirms an order, reduces stock, and moves it to Diproses', function () {
    $product = Product::factory()->create(['stock' => 20]);
    $order = Order::factory()->create(['status' => OrderStatus::New]);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => 5,
        'subtotal' => $product->price * 5,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('confirm');

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
    expect($product->fresh()->stock)->toBe(15);
});

it('shows a friendly error instead of a 500 when stock is insufficient at confirm time', function () {
    $product = Product::factory()->create(['stock' => 2]);
    $order = Order::factory()->create(['status' => OrderStatus::New]);
    $order->items()->create([
        'product_id' => $product->id,
        'product_name' => $product->name,
        'price' => $product->price,
        'quantity' => 5,
        'subtotal' => $product->price * 5,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('confirm')
        ->assertHasErrors('stock');

    expect($order->fresh()->status)->toBe(OrderStatus::New);
});

it('auto-assigns the nearest area\'s sales when confirming an order with a pinpoint', function () {
    $area = Area::factory()->create(['name' => 'Jakarta Pusat', 'lat' => -6.1805, 'lng' => 106.8284, 'radius_km' => 10]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->for($sales)->for($area)->create();

    $order = Order::factory()->create([
        'status' => OrderStatus::New,
        'delivery_lat' => -6.1805,
        'delivery_lng' => 106.8284,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('confirm');

    expect($order->fresh()->sales_id)->toBe($sales->id);
    expect($order->fresh()->sales_assigned_at)->not->toBeNull();
});

it('does not override a manually assigned sales when confirming', function () {
    $area = Area::factory()->create(['lat' => -6.1805, 'lng' => 106.8284, 'radius_km' => 10]);
    $autoSales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->for($autoSales)->for($area)->create();

    $manualSales = User::factory()->create(['role' => Role::Sales]);

    $order = Order::factory()->create([
        'status' => OrderStatus::New,
        'delivery_lat' => -6.1805,
        'delivery_lng' => 106.8284,
    ]);

    $component = Livewire::actingAs($this->admin)->test(Show::class, ['order' => $order]);
    $component->set('salesUserId', $manualSales->id)->call('assignSales');
    $component->call('confirm');

    expect($order->fresh()->sales_id)->toBe($manualSales->id);
});

it('moves an order through Dikirim to Selesai in order', function () {
    Storage::fake('s3');
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    $component = Livewire::actingAs($this->admin)->test(Show::class, ['order' => $order]);

    $component->call('markShipped');
    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
    expect($order->fresh()->estimated_delivery_at?->toDateTimeString())->toBe($order->estimated_delivery_at?->toDateTimeString()); // the system's estimate from checkout, untouched

    $component->set('deliveryProof', UploadedFile::fake()->image('bukti.jpg'))->call('markCompleted');
    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    expect($order->fresh()->delivery_proof_path)->not->toBeNull();
});

it('requires a delivery proof photo before marking an order Selesai', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('markCompleted')
        ->assertHasErrors('deliveryProof');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('assigns a sales user to a Processing order', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['status' => OrderStatus::Processing]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->set('salesUserId', $sales->id)
        ->call('assignSales');

    expect($order->fresh()->sales_id)->toBe($sales->id);
});

it('restocks items when a rejected order is verified in good condition', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::RejectedByUser]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => $product->price, 'quantity' => 4, 'subtotal' => $product->price * 4,
    ]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id, 'tipe' => ComplaintType::TolakSaatTerima,
        'status' => ComplaintStatus::Diajukan, 'alasan' => 'Salah alamat',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('resolveRejection', 'good');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(14);
    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Diresolusi);
    expect($complaint->fresh()->resolusi)->toBe('restock');
});

it('does not restock items when a rejected order is verified as damaged', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create(['status' => OrderStatus::RejectedByUser]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => $product->price, 'quantity' => 4, 'subtotal' => $product->price * 4,
    ]);
    $complaint = Complaint::factory()->create([
        'order_id' => $order->id, 'tipe' => ComplaintType::TolakSaatTerima,
        'status' => ComplaintStatus::Diajukan, 'alasan' => 'Barang rusak',
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('resolveRejection', 'damaged');

    expect($order->fresh()->status)->toBe(OrderStatus::Cancelled);
    expect($product->fresh()->stock)->toBe(10);
    expect($product->stockMovements()->where('type', \App\Enums\StockMovementType::WriteOff)->count())->toBe(1);
    expect($complaint->fresh()->status)->toBe(ComplaintStatus::Diresolusi);
    expect($complaint->fresh()->resolusi)->toBe('writeoff');
});

it('blocks non-admin roles from the orders index', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer)
        ->get('http://admin.'.config('app.domain').'/orders')
        ->assertStatus(403);
});

it('blocks confirming a digital order that has not been paid yet', function () {
    $order = Order::factory()->create([
        'status' => OrderStatus::New, 'payment_method' => 'qris', 'payment_status' => PaymentStatus::Pending,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('confirm')
        ->assertHasErrors('stock');

    expect($order->fresh()->status)->toBe(OrderStatus::New);
});

it('allows confirming a digital order once it is paid', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $order = Order::factory()->create([
        'status' => OrderStatus::New, 'payment_method' => 'qris', 'payment_status' => PaymentStatus::Paid,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => $product->price, 'quantity' => 2, 'subtotal' => $product->price * 2,
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['order' => $order])->call('confirm');

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('no longer awards points when Admin marks the order Completed', function () {
    Storage::fake('s3');
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 0]);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped, 'total' => 15000]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->set('deliveryProof', UploadedFile::fake()->image('bukti.jpg'))
        ->call('markCompleted');

    expect($buyer->buyerProfile->fresh()->loyalty_points)->toBe(0); // points come from toys sold since 2026-10-23
});

it('sets completed_at when Admin marks the order Completed', function () {
    Storage::fake('s3');
    $order = Order::factory()->create(['status' => OrderStatus::Shipped]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->set('deliveryProof', UploadedFile::fake()->image('bukti.jpg'))
        ->call('markCompleted');

    expect($order->fresh()->completed_at)->not->toBeNull();
});

it('blocks Admin from marking a Sales-assigned order shipped before Sales confirms receipt from Gudang', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Processing, 'sales_id' => $sales->id, 'sales_received_at' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('markShipped')
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('allows Admin to mark a Sales-assigned order shipped once Sales has confirmed receipt', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Processing, 'sales_id' => $sales->id, 'sales_received_at' => now(),
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['order' => $order])->call('markShipped');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('blocks Admin from using the direct markCompleted path on a Sales-assigned order', function () {
    Storage::fake('s3');
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'sales_id' => $sales->id]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->set('deliveryProof', UploadedFile::fake()->image('bukti.jpg'))
        ->call('markCompleted')
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('lets Admin close a Sales-assigned order once Sales delivered and the buyer confirmed', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 0]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Shipped, 'sales_id' => $sales->id, 'buyer_id' => $buyer->id, 'total' => 15000,
        'sales_delivered_at' => now(), 'buyer_confirmed_at' => now(),
    ]);

    Livewire::actingAs($this->admin)->test(Show::class, ['order' => $order])->call('closeOrder');

    expect($order->fresh()->status)->toBe(OrderStatus::Completed);
    expect($order->fresh()->completed_at)->not->toBeNull();
    expect($buyer->buyerProfile->fresh()->loyalty_points)->toBe(0); // orders no longer earn points
});

it('blocks closing an order before the buyer has confirmed receipt', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'status' => OrderStatus::Shipped, 'sales_id' => $sales->id,
        'sales_delivered_at' => now(), 'buyer_confirmed_at' => null,
    ]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('closeOrder')
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('lets Admin acknowledge a payment-review flag, clearing it', function () {
    $order = Order::factory()->create(['needs_payment_review' => true]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('acknowledgePaymentReview');

    expect($order->fresh()->needs_payment_review)->toBeFalse();
});

it('blocks acknowledging payment review on an order that was never flagged', function () {
    $order = Order::factory()->create(['needs_payment_review' => false]);

    Livewire::actingAs($this->admin)
        ->test(Show::class, ['order' => $order])
        ->call('acknowledgePaymentReview')
        ->assertForbidden();
});
