<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Home;
use App\Models\BuyerProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;
use Livewire\Livewire;

function railBuyer(): User
{
    $user = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $user->id]);

    return $user;
}

function railOrder(User $buyer, array $products, string $status = 'completed', $at = null): Order
{
    $at ??= now()->subDays(20);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => $status, 'completed_at' => $status === 'completed' ? $at : null]);
    $order->forceFill(['created_at' => $at])->save();
    foreach ($products as $product) {
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 3]);
    }

    return $order;
}

it('recommends products co-bought with the buyer history, skipping recent buys and inactive ones', function () {
    $buyer = railBuyer();
    $other = railBuyer();
    [$a, $b, $c, $recent, $inactive] = Product::factory()->count(5)->create()->all();
    $inactive->update(['is_active' => false]);
    railOrder($buyer, [$a], at: now()->subDays(40));
    railOrder($buyer, [$recent], at: now()->subDays(3));
    railOrder($other, [$a, $b, $c, $recent, $inactive]);

    $ids = Catalog::recommendedFor($buyer, 10)->pluck('id');
    expect($ids->take(2)->sort()->values()->all())->toBe(collect([$b->id, $c->id])->sort()->values()->all())
        ->and($ids)->not->toContain($recent->id)
        ->and($ids)->not->toContain($inactive->id)
        ->and($ids->unique()->count())->toBe($ids->count());
});

it('tops up recommendations for a buyer with no history', function () {
    Product::factory()->count(12)->create();
    expect(Catalog::recommendedFor(railBuyer(), 10))->toHaveCount(10);
});

it('picks the buyer top categories with rail products, and falls back for guests', function () {
    $buyer = railBuyer();
    $kopi = Category::factory()->create(['name' => 'Kopi & Teh']);
    $mie = Category::factory()->create(['name' => 'Mie Instan']);
    $thin = Category::factory()->create(['name' => 'Tipis']);
    $kopiProducts = Product::factory()->count(4)->create(['category_id' => $kopi->id]);
    Product::factory()->count(5)->create(['category_id' => $mie->id]);
    Product::factory()->count(2)->create(['category_id' => $thin->id]);
    railOrder($buyer, [$kopiProducts[0]]);

    $mine = Catalog::topCategoriesFor($buyer, 2);
    expect($mine->first()->name)->toBe('Kopi & Teh')
        ->and($mine->pluck('name'))->not->toContain('Tipis')
        ->and($mine->first()->railProducts->count())->toBeGreaterThanOrEqual(3);

    expect(Catalog::topCategoriesFor(null, 3)->pluck('name'))->not->toContain('Tipis');
});

it('tops best sellers up to the limit when only a few products sold recently', function () {
    $products = Product::factory()->count(10)->create();
    railOrder(railBuyer(), $products->take(3)->all(), at: now()->subDays(5));

    $best = Catalog::bestSellers(null, 6)['products'];
    expect($best)->toHaveCount(6)
        ->and($best->take(3)->pluck('id')->sort()->values()->all())->toBe($products->take(3)->pluck('id')->sort()->values()->all());
});

it('shows guests the public sections only', function () {
    $products = Product::factory()->count(30)->create();
    railOrder(railBuyer(), $products->take(4)->all(), at: now()->subDays(5));
    Livewire::test(Home::class)
        ->assertSee('Terlaris')->assertSee('Produk Baru')->assertSee('Jelajahi Semua')
        ->assertDontSee('Rekomendasi untuk Toko Anda')->assertDontSee('Beli Lagi');
});

it('shows a buyer the active order row and personal rails', function () {
    $buyer = railBuyer();
    $products = Product::factory()->count(30)->create();
    railOrder($buyer, $products->take(4)->all(), status: OrderStatus::Shipped->value, at: now()->subDay());
    railOrder($buyer, $products->slice(4, 4)->all(), at: now()->subDays(60));
    railOrder(railBuyer(), $products->slice(4, 10)->all());

    Livewire::actingAs($buyer)->test(Home::class)
        ->assertSee('sedang diantar')->assertSee('Rekomendasi untuk Toko Anda')->assertSee('Beli Lagi');
});

it('loads 20 more products into the explore grid and never repeats rail products there', function () {
    Product::factory()->count(90)->create();
    $home = Livewire::test(Home::class);
    $first = $home->viewData('explore')->count();
    $home->call('loadMore');

    expect($home->viewData('explore')->count())->toBe($first + 20)
        ->and($home->viewData('explore')->pluck('id')->intersect($home->viewData('railIds')))->toBeEmpty();
});

it('shows the cart bar on Home only when the cart has items', function () {
    $product = Product::factory()->create(['stock' => 20]);
    Livewire::test(Home::class)->assertDontSee('Lihat keranjang');

    \App\Support\Cart::add($product->id, 2);
    Livewire::test(Home::class)->assertSee('Lihat keranjang');
});
