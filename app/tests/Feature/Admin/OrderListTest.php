<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Admin\Orders\Index;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

function listedIds($component): array
{
    return collect($component->viewData('orders')->items())->pluck('id')->all();
}

it('searches orders by exact number', function () {
    $hit = Order::factory()->create(['id' => 4321]);
    Order::factory()->create(['id' => 43210]);

    $c = Livewire::actingAs($this->admin)->test(Index::class)->set('search', '4321');

    expect(listedIds($c))->toBe([$hit->id]);
});

it('searches orders by store name', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Toko Melati']);
    $hit = Order::factory()->create(['buyer_id' => $buyer->id]);
    Order::factory()->create();

    expect(listedIds(Livewire::actingAs($this->admin)->test(Index::class)->set('search', 'melati')))->toBe([$hit->id]);
});

it('searches orders by a phone typed with a leading 0 and dashes', function () {
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'phone' => '6281234567890']);
    $hit = Order::factory()->create(['buyer_id' => $buyer->id]);
    Order::factory()->create();

    expect(listedIds(Livewire::actingAs($this->admin)->test(Index::class)->set('search', '0812-3456-7890')))->toBe([$hit->id]);
});

it('groups cancelled and rejected orders under one Batal/Ditolak tab', function () {
    $cancelled = Order::factory()->create(['status' => OrderStatus::Cancelled]);
    $rejected = Order::factory()->create(['status' => OrderStatus::RejectedByUser]);
    Order::factory()->create(['status' => OrderStatus::New]);

    $c = Livewire::actingAs($this->admin)->test(Index::class)->call('setStatusFilter', 'closed');

    expect(listedIds($c))->toEqualCanonicalizing([$cancelled->id, $rejected->id]);
    $c->assertSeeHtmlInOrder(['Batal/Ditolak', '<span>2</span>']);
});

it('shows the sales, the status pill and the action flag on each row', function () {
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Andi Sales']);
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => $sales->id]);
    Order::factory()->create(['status' => OrderStatus::New, 'needs_payment_review' => true, 'created_at' => now()->subHours(5)]);

    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSee('Andi Sales')
        ->assertSeeHtml('adm-pill tone-info')
        ->assertSeeHtml('adm-flag is-late')
        ->assertSee('Review pembayaran');
});

it('counts orders in flight in the header', function () {
    Order::factory()->create(['status' => OrderStatus::New]);
    Order::factory()->create(['status' => OrderStatus::Shipped]);
    Order::factory()->create(['status' => OrderStatus::Completed]);

    Livewire::actingAs($this->admin)->test(Index::class)->assertViewHas('inFlight', 2)->assertSee('2 sedang jalan');
});

it('links every row to the order detail', function () {
    $order = Order::factory()->create();

    Livewire::actingAs($this->admin)->test(Index::class)->assertSeeHtml('href="'.route('admin.orders.show', $order).'"');
});
