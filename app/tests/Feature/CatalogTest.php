<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Catalog;

function catalogBuyer(Area $area): User
{
    $user = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $user->id, 'area_id' => $area->id]);

    return $user;
}

/** @param array<int, array{0: Product, 1: int}> $lines */
function catalogOrder(User $buyer, array $lines, OrderStatus $status = OrderStatus::Completed, $completedAt = null): Order
{
    $order = Order::factory()->create([
        'buyer_id' => $buyer->id,
        'status' => $status,
        'completed_at' => $status === OrderStatus::Completed ? ($completedAt ?? now()->subDay()) : null,
    ]);

    foreach ($lines as [$product, $qty]) {
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $qty]);
    }

    return $order;
}

it('lists categories with a count of active products only', function () {
    $category = Category::factory()->create(['name' => 'Minuman']);
    Product::factory()->count(2)->create(['category_id' => $category->id, 'is_active' => true]);
    Product::factory()->create(['category_id' => $category->id, 'is_active' => false]);

    expect(Catalog::categories()->firstWhere('name', 'Minuman')->products_count)->toBe(2);
});

it('lists active promos by highest discount percent, skipping expired ones', function () {
    $small = Product::factory()->create(['price' => 10000, 'discount_price' => 9500]);
    $big = Product::factory()->create(['price' => 10000, 'discount_price' => 7000]);
    Product::factory()->create(['price' => 10000, 'discount_price' => 5000, 'discount_ends_at' => now()->subDay()]);
    Product::factory()->create(['price' => 10000, 'discount_price' => 5000, 'is_active' => false]);

    expect(Catalog::promos()->pluck('id')->all())->toBe([$big->id, $small->id]);
});

it('ranks best sellers within the buyer\'s area', function () {
    $area = Area::factory()->create();
    $otherArea = Area::factory()->create();
    $buyer = catalogBuyer($area);
    $a = Product::factory()->create();
    $b = Product::factory()->create();

    catalogOrder(catalogBuyer($area), [[$a, 5], [$b, 1]]);
    catalogOrder(catalogBuyer($otherArea), [[$b, 50]]);

    $best = Catalog::bestSellers($buyer);

    expect($best['title'])->toBe('Terlaris di '.$area->name);
    expect($best['products']->pluck('id')->all())->toBe([$a->id, $b->id]);
});

it('ignores orders older than 30 days, not completed, or for inactive products', function () {
    $area = Area::factory()->create();
    $counted = Product::factory()->create();
    $old = Product::factory()->create();
    $pending = Product::factory()->create();
    $inactive = Product::factory()->create(['is_active' => false]);

    catalogOrder(catalogBuyer($area), [[$counted, 1]]);
    catalogOrder(catalogBuyer($area), [[$old, 99]], OrderStatus::Completed, now()->subDays(31));
    catalogOrder(catalogBuyer($area), [[$pending, 99]], OrderStatus::Processing);
    catalogOrder(catalogBuyer($area), [[$inactive, 99]]);

    // The 30-day completed sale ranks first; older and in-progress ones only top the rail up
    // afterwards, and inactive products never appear.
    $ids = Catalog::bestSellers(catalogBuyer($area))['products']->pluck('id');
    expect($ids->first())->toBe($counted->id)
        ->and($ids)->not->toContain($inactive->id);
});

it('falls back to global best sellers, then to the newest products', function () {
    $buyerArea = Area::factory()->create();
    $product = Product::factory()->create();
    catalogOrder(catalogBuyer(Area::factory()->create()), [[$product, 3]]);

    $global = Catalog::bestSellers(catalogBuyer($buyerArea));
    expect($global['title'])->toBe('Terlaris');
    expect($global['products']->pluck('id')->all())->toBe([$product->id]);

    OrderItem::query()->delete();
    Order::query()->delete();
    $newest = Product::factory()->create();

    $fallback = Catalog::bestSellers(null);
    expect($fallback['title'])->toBe('Produk Terbaru');
    expect($fallback['products']->first()->id)->toBe($newest->id);
});

it('builds Beli Lagi from the buyer\'s last 5 non-cancelled orders, newest first', function () {
    $area = Area::factory()->create();
    $buyer = catalogBuyer($area);
    $tooOld = Product::factory()->create();
    $recent = Product::factory()->create();
    $cancelled = Product::factory()->create();
    $deactivated = Product::factory()->create(['is_active' => false]);

    catalogOrder($buyer, [[$tooOld, 1]]);
    foreach (range(1, 4) as $_) {
        catalogOrder($buyer, [[$deactivated, 1]]);
    }
    catalogOrder($buyer, [[$recent, 3]]);
    catalogOrder($buyer, [[$cancelled, 1]], OrderStatus::Cancelled);

    $lines = Catalog::buyAgain($buyer);

    expect($lines->pluck('product.id')->all())->toBe([$recent->id]);
    expect($lines->first()['quantity'])->toBe(3);
});

it('returns nothing for Beli Lagi when the buyer has no orders', function () {
    expect(Catalog::buyAgain(catalogBuyer(Area::factory()->create())))->toBeEmpty();
});

it('does not bind one placeholder per order when finding products bought together', function () {
    $area = Area::factory()->create();
    $main = Product::factory()->create();
    $other = Product::factory()->create();
    foreach (range(1, 25) as $_) {
        catalogOrder(catalogBuyer($area), [[$main, 1], [$other, 1]]);
    }

    $maxBindings = 0;
    \Illuminate\Support\Facades\DB::listen(function ($query) use (&$maxBindings) {
        $maxBindings = max($maxBindings, count($query->bindings));
    });

    expect(Catalog::boughtWith($main)->pluck('id')->all())->toContain($other->id);
    expect($maxBindings)->toBeLessThan(25);
});

it('ranks products bought together, then fills from the same category', function () {
    $area = Area::factory()->create();
    $category = Category::factory()->create();
    $main = Product::factory()->create(['category_id' => $category->id]);
    $often = Product::factory()->create();
    $once = Product::factory()->create();
    $sibling = Product::factory()->create(['category_id' => $category->id, 'name' => 'AAA sibling']);

    catalogOrder(catalogBuyer($area), [[$main, 1], [$often, 1], [$once, 1]]);
    catalogOrder(catalogBuyer($area), [[$main, 1], [$often, 1]]);

    $related = Catalog::boughtWith($main, 3);

    expect($related->pluck('id')->all())->toBe([$often->id, $once->id, $sibling->id]);
    expect($related->pluck('id'))->not->toContain($main->id);
});
