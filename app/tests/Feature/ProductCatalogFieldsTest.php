<?php

use App\Models\Product;
use App\Models\ProductPhoto;
use App\Support\Cart;

it('generates a slug from the name when a product is created', function () {
    $product = Product::factory()->create(['name' => 'Mie Gaga 100 Ayam Bawang']);

    expect($product->slug)->toBe('mie-gaga-100-ayam-bawang');
    expect($product->url())->toEndWith('/produk/mie-gaga-100-ayam-bawang');
});

it('suffixes the slug when the name is already taken', function () {
    Product::factory()->create(['name' => 'Teh Pucuk']);
    $second = Product::factory()->create(['name' => 'Teh Pucuk']);
    $third = Product::factory()->create(['name' => 'Teh Pucuk']);

    expect($second->slug)->toBe('teh-pucuk-2');
    expect($third->slug)->toBe('teh-pucuk-3');
});

it('falls back to "produk" when the name has no sluggable characters', function () {
    expect(Product::factory()->create(['name' => '!!!'])->slug)->toBe('produk');
});

it('keeps the slug when the product is renamed', function () {
    $product = Product::factory()->create(['name' => 'Kopi Lama']);
    $product->update(['name' => 'Kopi Baru']);

    expect($product->fresh()->slug)->toBe('kopi-lama');
});

it('treats a discount as active only until discount_ends_at', function () {
    $open = Product::factory()->create(['price' => 10000, 'discount_price' => 9000, 'discount_ends_at' => null]);
    $future = Product::factory()->create(['price' => 10000, 'discount_price' => 9000, 'discount_ends_at' => now()->addDay()]);
    $past = Product::factory()->create(['price' => 10000, 'discount_price' => 9000, 'discount_ends_at' => now()->subMinute()]);

    expect($open->hasDiscount())->toBeTrue();
    expect($future->hasDiscount())->toBeTrue();
    expect($past->hasDiscount())->toBeFalse();
    expect($past->effectivePrice())->toBe(10000);
    expect(Product::promo()->pluck('id')->all())->toEqualCanonicalizing([$open->id, $future->id]);
});

it('computes the discount percent and whole days left', function () {
    $product = Product::factory()->create([
        'price' => 119200,
        'discount_price' => 109600,
        'discount_ends_at' => now()->addDays(3)->subHour(),
    ]);

    expect($product->discountPercent())->toBe(8);
    expect($product->discountDaysLeft())->toBe(3);
});

it('has no days left when the discount has no end date', function () {
    $product = Product::factory()->create(['price' => 10000, 'discount_price' => 9000]);

    expect($product->discountDaysLeft())->toBeNull();
});

it('computes a per-piece price only when pieces_per_unit is set', function () {
    $carton = Product::factory()->create(['price' => 109600, 'pieces_per_unit' => 40]);
    $loose = Product::factory()->create(['price' => 29500, 'pieces_per_unit' => null]);

    expect($carton->pricePerPiece())->toBe(2740);
    expect($loose->pricePerPiece())->toBeNull();
});

it('treats a product whose stock is below its minimum purchase as unavailable', function () {
    $short = Product::factory()->create(['stock' => 1, 'min_purchase' => 2]);
    $fine = Product::factory()->create(['stock' => 2, 'min_purchase' => 2]);
    $noMin = Product::factory()->create(['stock' => 1, 'min_purchase' => null]);

    expect($short->minQuantity())->toBe(2);
    expect($short->isAvailable())->toBeFalse();
    expect($fine->isAvailable())->toBeTrue();
    expect($noMin->minQuantity())->toBe(1);
    expect($noMin->isAvailable())->toBeTrue();
});

it('formats the size label without a trailing .0', function () {
    expect(Product::factory()->create(['size_value' => 75, 'size_unit' => 'g'])->sizeLabel())->toBe('75 g');
    expect(Product::factory()->create(['size_value' => 1.5, 'size_unit' => 'l'])->sizeLabel())->toBe('1.5 l');
    expect(Product::factory()->create(['size_value' => null])->sizeLabel())->toBeNull();
});

it('orders extra photos by sort_order and lists the cover first in the gallery', function () {
    $product = Product::factory()->create(['photo_path' => 'products/cover.jpg']);
    ProductPhoto::create(['product_id' => $product->id, 'path' => 'products/b.jpg', 'sort_order' => 2]);
    ProductPhoto::create(['product_id' => $product->id, 'path' => 'products/a.jpg', 'sort_order' => 1]);

    expect($product->photos->pluck('path')->all())->toBe(['products/a.jpg', 'products/b.jpg']);
    expect($product->galleryPaths())->toBe(['products/cover.jpg', 'products/a.jpg', 'products/b.jpg']);
});

it('exposes the cart quantities per product', function () {
    $product = Product::factory()->create(['stock' => 10]);
    Cart::add($product->id, 3);

    expect(Cart::quantities())->toBe([$product->id => 3]);
});
