<?php

use App\Enums\Role;
use App\Enums\StockMovementType;
use App\Livewire\Marketplace\ProductShow;
use App\Livewire\Marketplace\ProductsIndex;
use App\Models\Product;
use App\Models\StockAlert;
use App\Models\User;
use Livewire\Livewire;

function alertBuyer(): User
{
    return User::factory()->create(['role' => Role::User]);
}

it('toggles a restock alert from the product page', function () {
    $buyer = alertBuyer();
    $product = Product::factory()->create(['stock' => 0]);

    $page = Livewire::actingAs($buyer)->test(ProductShow::class, ['product' => $product])
        ->assertSee('Kabari saya')
        ->call('toggleStockAlert', $product->id)
        ->assertSee('Dikabari');
    expect(StockAlert::where('user_id', $buyer->id)->where('product_id', $product->id)->exists())->toBeTrue();

    $page->call('toggleStockAlert', $product->id)->assertSee('Kabari saya');
    expect(StockAlert::count())->toBe(0);
});

it('sends guests to login instead of subscribing', function () {
    $product = Product::factory()->create(['stock' => 0]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->call('toggleStockAlert', $product->id)
        ->assertRedirect(route('login'));
    expect(StockAlert::count())->toBe(0);
});

it('shows Kabari saya and the subscribed state on sold-out cards', function () {
    $buyer = alertBuyer();
    $waiting = Product::factory()->create(['stock' => 0, 'name' => 'Habis Ditunggu']);
    Product::factory()->create(['stock' => 0, 'name' => 'Habis Biasa']);
    StockAlert::create(['user_id' => $buyer->id, 'product_id' => $waiting->id]);

    Livewire::actingAs($buyer)->test(ProductsIndex::class)
        ->assertSeeInOrder(['Habis Ditunggu', 'Dikabari'])
        ->assertSee('Kabari saya');
});

it('ignores a toggle for a product that does not exist', function () {
    $product = Product::factory()->create(['stock' => 0]);

    Livewire::actingAs(alertBuyer())->test(ProductShow::class, ['product' => $product])
        ->call('toggleStockAlert', 999999);
    expect(StockAlert::count())->toBe(0);
});

it('notifies every subscriber once when the product becomes buyable, then drops their alerts', function () {
    [$a, $b, $other] = [alertBuyer(), alertBuyer(), alertBuyer()];
    $product = Product::factory()->create(['name' => 'Gulaku 1 kg', 'stock' => 0, 'min_purchase' => 1]);
    StockAlert::create(['user_id' => $a->id, 'product_id' => $product->id]);
    StockAlert::create(['user_id' => $b->id, 'product_id' => $product->id]);

    $product->adjustStock(10, StockMovementType::In, User::factory()->create(['role' => Role::Gudang]));

    foreach ([$a, $b] as $buyer) {
        $data = $buyer->notifications()->pluck('data')->all();
        expect($data)->toHaveCount(1)
            ->and($data[0]['body'])->toBe('Gulaku 1 kg tersedia lagi')
            ->and($data[0]['url'])->toBe('/produk/'.$product->slug);
    }
    expect($other->notifications()->count())->toBe(0)
        ->and(StockAlert::count())->toBe(0);

    $product->adjustStock(5, StockMovementType::In, User::factory()->create(['role' => Role::Gudang]));
    expect($a->notifications()->count())->toBe(1);
});

it('keeps waiting while stock is still below the minimum order', function () {
    $buyer = alertBuyer();
    $product = Product::factory()->create(['stock' => 0, 'min_purchase' => 5]);
    StockAlert::create(['user_id' => $buyer->id, 'product_id' => $product->id]);

    $product->update(['stock' => 2]);
    expect($buyer->notifications()->count())->toBe(0)
        ->and(StockAlert::count())->toBe(1);

    $product->update(['stock' => 5]);
    expect($buyer->notifications()->count())->toBe(1)
        ->and(StockAlert::count())->toBe(0);
});

it('does not notify for an inactive product', function () {
    $buyer = alertBuyer();
    $product = Product::factory()->create(['stock' => 0, 'is_active' => false]);
    StockAlert::create(['user_id' => $buyer->id, 'product_id' => $product->id]);

    $product->update(['stock' => 10]);

    expect($buyer->notifications()->count())->toBe(0);
});
