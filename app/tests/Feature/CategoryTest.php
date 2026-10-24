<?php

use App\Models\Category;
use App\Models\Product;

it('relates products to their category', function () {
    $category = Category::factory()->create(['name' => 'Minuman', 'color' => 'accent']);
    $product = Product::factory()->create(['category_id' => $category->id]);

    expect($product->category->name)->toBe('Minuman');
    expect($category->products()->count())->toBe(1);
});

it('enforces unique category names', function () {
    Category::factory()->create(['name' => 'Snack']);

    expect(fn () => Category::create(['name' => 'Snack', 'color' => 'accent', 'icon' => 'cookie']))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
