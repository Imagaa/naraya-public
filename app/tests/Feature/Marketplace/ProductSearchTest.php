<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\ProductSearch;

function searchNames(string $term): array
{
    return Product::whereIn('id', array_keys(ProductSearch::rank($term)))->pluck('name', 'id')
        ->sortBy(fn ($name, $id) => array_search($id, array_keys(ProductSearch::rank($term))))
        ->values()->all();
}

beforeEach(function () {
    $this->noodles = Category::factory()->create(['name' => 'Mie Instan']);
    $this->soap = Category::factory()->create(['name' => 'Sabun & Toiletries']);
    Product::factory()->create(['name' => 'Indomie Goreng', 'category_id' => $this->noodles->id]);
    Product::factory()->create(['name' => 'Kopi Kapal Api Special', 'category_id' => $this->noodles->id]);
    Product::factory()->create(['name' => 'Lifebuoy Body Wash', 'category_id' => $this->soap->id]);
    Product::factory()->create(['name' => 'Ultra Milk Coklat 250ml', 'category_id' => $this->noodles->id]);
});

it('finds a product while the word is still being typed or mistyped', function () {
    expect(searchNames('indomi'))->toBe(['Indomie Goreng'])
        ->and(searchNames('indomei'))->toBe(['Indomie Goreng'])
        ->and(searchNames('kopi kapl'))->toBe(['Kopi Kapal Api Special']);
});

it('ignores word order and letter case', function () {
    expect(searchNames('KAPAL kopi'))->toBe(['Kopi Kapal Api Special']);
});

it('matches through the category name', function () {
    expect(searchNames('sabun'))->toBe(['Lifebuoy Body Wash']);
});

it('matches synonyms', function () {
    expect(searchNames('susu'))->toBe(['Ultra Milk Coklat 250ml'])
        ->and(searchNames('chocolate'))->toBe(['Ultra Milk Coklat 250ml']);
});

it('ranks a closer match above a looser one', function () {
    Product::factory()->create(['name' => 'Mie Sedaap Goreng', 'category_id' => $this->noodles->id]);

    expect(searchNames('indomie goreng')[0])->toBe('Indomie Goreng');
});

it('returns nothing for gibberish, blank input or inactive products', function () {
    Product::factory()->create(['name' => 'Sarden Botan', 'is_active' => false]);

    expect(ProductSearch::rank('qzxv wplk'))->toBe([])
        ->and(ProductSearch::rank('   '))->toBe([])
        ->and(ProductSearch::rank('sarden'))->toBe([]);
});

it('finds a product by its exact barcode', function () {
    $product = Product::factory()->create(['name' => 'Aqua 600ml', 'barcode' => '8886008101053']);

    expect(array_keys(ProductSearch::rank('8886008101053')))->toBe([$product->id]);
});

it('sees a renamed product right away', function () {
    $product = Product::where('name', 'Lifebuoy Body Wash')->first();
    expect(ProductSearch::rank('nuvo'))->toBe([]);

    $this->travel(1)->seconds();
    $product->update(['name' => 'Nuvo Body Wash']);

    expect(array_keys(ProductSearch::rank('nuvo')))->toBe([$product->id]);
});

it('ranks a product named after the word above one that only matches by category', function () {
    $drinks = Category::factory()->create(['name' => 'Kopi & Teh']);
    Product::factory()->create(['name' => 'Bendera Susu Jahe', 'category_id' => $drinks->id]);
    Product::factory()->create(['name' => 'Kapal Api Kopi Susu', 'category_id' => $drinks->id]);

    // "kopi" is only in the first product's category, but in the second one's name
    expect(searchNames('susu kopi'))->toBe(['Kapal Api Kopi Susu', 'Bendera Susu Jahe']);
});

it('breaks a tie with the better seller of the last 90 days', function () {
    $slow = Product::factory()->create(['name' => 'Aqua Botol 600ml']);
    $fast = Product::factory()->create(['name' => 'Aqua Botol 1500ml']);
    $order = \App\Models\Order::factory()->create(['status' => 'completed', 'completed_at' => now()->subDays(3)]);
    \App\Models\OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $fast->id, 'quantity' => 5]);

    expect(array_keys(ProductSearch::rank('aqua botol')))->toBe([$fast->id, $slow->id]);
});
