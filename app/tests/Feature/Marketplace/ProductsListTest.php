<?php

use App\Livewire\Marketplace\ProductShow;
use App\Livewire\Marketplace\ProductsIndex;
use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;

it('paginates 24 products per page', function () {
    Product::factory()->count(30)->create();
    $list = Livewire::test(ProductsIndex::class);

    expect($list->viewData('products')->count())->toBe(24)
        ->and($list->viewData('products')->total())->toBe(30);
    $list->assertSee('30 produk');
});

it('sorts by price and filters to available products', function () {
    Product::factory()->create(['name' => 'Produk Mahal', 'price' => 90000, 'stock' => 50]);
    Product::factory()->create(['name' => 'Produk Murah', 'price' => 10000, 'stock' => 50]);
    Product::factory()->create(['name' => 'Produk Habis', 'price' => 50000, 'stock' => 0]);

    Livewire::test(ProductsIndex::class)->set('sort', 'termurah')->assertSeeInOrder(['Produk Murah', 'Produk Habis', 'Produk Mahal']);
    Livewire::test(ProductsIndex::class)->set('sort', 'termahal')->assertSeeInOrder(['Produk Mahal', 'Produk Habis', 'Produk Murah']);
    Livewire::test(ProductsIndex::class)->set('available', true)->assertDontSee('Produk Habis')->assertSee('Produk Murah');
});

it('sorts by the effective (discounted) price', function () {
    Product::factory()->create(['name' => 'Diskon Besar', 'price' => 90000, 'discount_price' => 5000, 'discount_ends_at' => now()->addDay()]);
    Product::factory()->create(['name' => 'Normal Sedang', 'price' => 20000]);

    Livewire::test(ProductsIndex::class)->set('sort', 'termurah')->assertSeeInOrder(['Diskon Besar', 'Normal Sedang']);
});

it('returns to page 1 when a filter changes', function () {
    Product::factory()->count(60)->create();

    Livewire::test(ProductsIndex::class)->call('gotoPage', 3)->assertSet('paginators.page', 3)
        ->set('sort', 'termurah')->assertSet('paginators.page', 1);
});

it('accepts the sort and filter from the URL', function () {
    Product::factory()->create(['name' => 'Produk Mahal', 'price' => 90000, 'stock' => 50]);
    $this->get('http://'.config('app.domain').'/produk?sort=termahal&available=1')->assertOk()->assertSee('Produk Mahal');
});

it('shows similar products and keeps the inline buy buttons desktop-only', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id, 'price' => 50000, 'stock' => 20]);
    // Without co-purchase data, "Sering dibeli bersama" takes 10 same-category products;
    // "Produk serupa" shows the rest and never repeats them.
    Product::factory()->count(14)->create(['category_id' => $category->id, 'price' => 52000]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSee('Produk serupa')
        ->assertSeeHtml('class="buy-btns hide-phone-flex"')
        ->assertSeeHtml('class="buybar"');
});

it('counts products without a minimum order as available (null min_purchase means 1)', function () {
    $noMinimum = Product::factory()->create(['name' => 'Tanpa Minimum', 'stock' => 5, 'min_purchase' => null]);
    expect($noMinimum->isAvailable())->toBeTrue()
        ->and(Product::available()->whereKey($noMinimum->id)->exists())->toBeTrue();
});
