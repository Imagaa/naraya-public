<?php

use App\Livewire\Marketplace\ProductsIndex;
use App\Models\Category;
use App\Models\Product;
use Livewire\Livewire;

it('filters by the search query from the header', function () {
    Product::factory()->create(['name' => 'Teh Pucuk Harum']);
    Product::factory()->create(['name' => 'Kopi Kapal Api']);

    $this->get('http://naraya.test/produk?q=pucuk')
        ->assertStatus(200)
        ->assertSee('Teh Pucuk Harum')
        ->assertDontSee('Kopi Kapal Api');
});

it('filters by category', function () {
    $drinks = Category::factory()->create(['name' => 'Minuman']);
    Product::factory()->create(['name' => 'Aqua 600 ml', 'category_id' => $drinks->id]);
    Product::factory()->create(['name' => 'Beras Pandan']);

    Livewire::test(ProductsIndex::class)
        ->set('category', 'Minuman')
        ->assertSee('Aqua 600 ml')
        ->assertDontSee('Beras Pandan');
});

it('shows an empty state for a search with no results and clears filters', function () {
    Product::factory()->create(['name' => 'Gula Pasir']);

    Livewire::test(ProductsIndex::class)
        ->set('q', 'zzz')
        ->assertSee("Tidak ada produk untuk 'zzz'")
        ->call('clearFilters')
        ->assertSet('q', '')
        ->assertSet('category', '')
        ->assertSee('Gula Pasir');
});

it('renders category chips safely for names with apostrophes', function () {
    $category = Category::factory()->create(['name' => "Kacang & Snack's"]);
    Product::factory()->create(['category_id' => $category->id]); // empty categories are hidden

    $html = Livewire::test(ProductsIndex::class)->html();

    expect($html)->toContain("\$set('category', 'Kacang \\u0026 Snack\\u0027s')");
});

it('keeps the header search text and active tab after a Livewire action', function () {
    $product = Product::factory()->create(['name' => 'Indomie Goreng', 'stock' => 10]);

    Livewire::withQueryParams(['q' => 'indomie'])
        ->test(ProductsIndex::class)
        ->assertSeeHtml('value="indomie"')
        // the search box is its own child component, so a parent action leaves its text alone
        ->call('addToCart', $product->id)
        ->assertSeeHtml('aria-current="page"');
});

it('no longer renders the old product modal', function () {
    Product::factory()->create();

    expect(Livewire::test(ProductsIndex::class)->html())->not->toContain('productModal');
});
