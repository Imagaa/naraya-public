<?php

use App\Enums\CashCollectionStatus;
use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Sales\Deliveries\Show;
use App\Models\BuyerProfile;
use App\Models\CashCollection;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('s3');
});

it('fetches a route from the given origin to the order pinpoint', function () {
    \Illuminate\Support\Facades\Http::fake([
        'api.openrouteservice.org/*' => \Illuminate\Support\Facades\Http::response([
            'features' => [
                ['geometry' => ['coordinates' => [[106.8, -6.2], [106.85, -6.25]]]],
            ],
        ]),
    ]);
    config(['services.openrouteservice.key' => 'test-key']);

    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Processing,
        'delivery_lat' => -6.25, 'delivery_lng' => 106.85,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->call('fetchRoute', -6.2, 106.8)
        ->assertSet('routePoints', [[-6.2, 106.8], [-6.25, 106.85]]);
});

it('falls back to the warehouse point when the origin is unavailable', function () {
    \App\Models\WarehouseSetting::first()->update(['lat' => -6.1, 'lng' => 106.7]);

    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Processing,
        'delivery_lat' => -6.25, 'delivery_lng' => 106.85,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->call('fetchRoute', null, null)
        ->assertSet('routePoints', [[-6.1, 106.7], [-6.25, 106.85]]);
});

it('blocks a sales user from acting on an order assigned to someone else', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $otherSales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $otherSales->id, 'status' => OrderStatus::Processing]);

    $this->actingAs($sales)
        ->get('http://sales.'.config('app.domain')."/deliveries/{$order->id}")
        ->assertStatus(403);
});

it('lets Sales confirm receiving the goods once Gudang has handed them over', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Processing,
        'gudang_handover_at' => now(),
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('receivedPhoto', UploadedFile::fake()->image('terima.jpg'))
        ->call('confirmReceivedFromGudang');

    expect($order->fresh()->sales_received_at)->not->toBeNull();
    expect($order->fresh()->sales_received_photo_path)->not->toBeNull();
});

it('blocks Sales from confirming receipt before Gudang hands the order over', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Processing, 'gudang_handover_at' => null,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('receivedPhoto', UploadedFile::fake()->image('terima.jpg'))
        ->call('confirmReceivedFromGudang')
        ->assertForbidden();
});

it('marks an assigned order as shipped once Sales has confirmed receipt from Gudang', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Processing, 'sales_received_at' => now(),
    ]);

    Livewire::actingAs($sales)->test(Show::class, ['order' => $order])->call('markShipped');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped);
});

it('blocks marking an order shipped before Sales has confirmed receipt from Gudang', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Processing, 'sales_received_at' => null,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->call('markShipped')
        ->assertForbidden();

    expect($order->fresh()->status)->toBe(OrderStatus::Processing);
});

it('confirms delivery to the buyer at the full ordered quantity — quantity is not editable here', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $product = Product::factory()->create();
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped]);
    $item = $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => $product->price, 'quantity' => 5, 'subtotal' => $product->price * 5,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('cashRecountConfirmed', true)
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer');

    expect($order->fresh()->status)->toBe(OrderStatus::Shipped); // not Completed yet
    expect($order->fresh()->sales_delivered_at)->not->toBeNull();
    expect($order->fresh()->sales_delivered_photo_path)->not->toBeNull();
    expect($item->fresh()->delivered_quantity)->toBe(5);
});

it('requires a photo before completing delivery to the buyer', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('cashRecountConfirmed', true)
        ->call('confirmDeliveredToBuyer')
        ->assertHasErrors('deliveredPhoto');

    expect($order->fresh()->sales_delivered_at)->toBeNull();
});

it('requires the cash recount checkbox before completing delivery on a cash order', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'payment_method' => 'cash']);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer')
        ->assertHasErrors('cashRecountConfirmed');

    expect($order->fresh()->sales_delivered_at)->toBeNull();
});

it('does not require the cash recount checkbox on a non-cash order', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'payment_method' => 'gopay']);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer')
        ->assertHasNoErrors('cashRecountConfirmed');

    expect($order->fresh()->sales_delivered_at)->not->toBeNull();
});

it('creates a held cash collection for the full order amount when a cash order is confirmed delivered', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'payment_method' => 'cash',
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => 2000, 'quantity' => 4, 'subtotal' => 8000,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('cashRecountConfirmed', true)
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer');

    $collection = CashCollection::where('order_id', $order->id)->first();
    expect($collection)->not->toBeNull();
    expect($collection->amount)->toBe(8000);
    expect($collection->status)->toBe(CashCollectionStatus::Held);
});

it('does not create a cash collection for a non-cash order', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Shipped, 'payment_method' => 'gopay',
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => 2000, 'quantity' => 4, 'subtotal' => 8000,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer');

    expect(CashCollection::where('order_id', $order->id)->exists())->toBeFalse();
});

it('reduces the cash collected by the order\'s credit_applied amount', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'status' => OrderStatus::Shipped,
        'payment_method' => 'cash', 'credit_applied' => 3000,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => 2500, 'quantity' => 4, 'subtotal' => 10000,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('cashRecountConfirmed', true)
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer');

    $collection = CashCollection::where('order_id', $order->id)->firstOrFail();
    expect($collection->amount)->toBe(7000); // 10000 item value - 3000 credit already applied at checkout
});

it('does not award loyalty points or record cash book income until Admin closes the order', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'loyalty_points' => 0]);
    $product = Product::factory()->create();
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'buyer_id' => $buyer->id, 'status' => OrderStatus::Shipped,
        'payment_method' => 'cash', 'total' => 25000,
    ]);
    $order->items()->create([
        'product_id' => $product->id, 'product_name' => $product->name,
        'price' => 25000, 'quantity' => 1, 'subtotal' => 25000,
    ]);

    Livewire::actingAs($sales)
        ->test(Show::class, ['order' => $order])
        ->set('cashRecountConfirmed', true)
        ->set('deliveredPhoto', UploadedFile::fake()->image('kirim.jpg'))
        ->call('confirmDeliveredToBuyer');

    expect($buyer->buyerProfile->fresh()->loyalty_points)->toBe(0);
    expect($order->fresh()->completed_at)->toBeNull();
});

it('shows who and where the buyer is, with the delivery pin for Maps', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Warung Pak Joko']);
    $buyer->buyerProfile()->create(['sales_user_id' => $sales->id, 'phone' => '081311112222']);
    $order = Order::factory()->create([
        'sales_id' => $sales->id, 'buyer_id' => $buyer->id, 'status' => OrderStatus::Processing,
        'delivery_address' => 'Jl. Mawar 9', 'delivery_lat' => -6.25, 'delivery_lng' => 106.85, 'total' => 50000,
    ]);

    Livewire::actingAs($sales)->test(Show::class, ['order' => $order])
        ->assertSee('Warung Pak Joko')
        ->assertSee('Jl. Mawar 9')
        ->assertSee('https://wa.me/6281311112222', false)
        ->assertSee('destination=-6.25', false)
        ->assertSee('Rp 50.000')
        ->assertSee('aria-current="step"', false)
        ->assertSee(route('sales.buyers.show', $buyer), false);
});

it('does not link to the store page when the buyer belongs to another sales user', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $buyer = User::factory()->create(['role' => Role::User]);
    $buyer->buyerProfile()->create(['sales_user_id' => User::factory()->create(['role' => Role::Sales])->id]);
    $order = Order::factory()->create(['sales_id' => $sales->id, 'buyer_id' => $buyer->id, 'status' => OrderStatus::Processing]);

    Livewire::actingAs($sales)->test(Show::class, ['order' => $order])
        ->assertDontSee(route('sales.buyers.show', $buyer), false);
});
