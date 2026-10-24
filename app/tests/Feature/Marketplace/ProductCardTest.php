<?php

use App\Enums\Role;
use App\Models\Category;
use App\Models\Product;
use App\Models\User;

function cardBuyer(): User
{
    return User::factory()->create(['role' => Role::User]);
}

it('shows unit price, per-piece price, size and minimum order', function () {
    $product = Product::factory()->create([
        'name' => 'Mie Gaga 100', 'price' => 119200, 'discount_price' => 109600,
        'unit' => 'karton', 'pieces_per_unit' => 40, 'min_purchase' => 2, 'stock' => 50,
        'size_value' => 75, 'size_unit' => 'g',
    ]);

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertSee('−8%')
        ->assertSee('Rp 119.200')
        ->assertSee('Rp 109.600')
        ->assertSee('/karton')
        ->assertSee('≈ Rp 2.740 per pcs')
        ->assertSee('75 g · isi 40')
        ->assertSee('Min. 2 karton')
        ->assertSee('/produk/mie-gaga-100')
        ->assertSee('data-product-id="'.$product->id.'"', false);
});

it('sends guests to login from the add button', function () {
    $product = Product::factory()->create(['stock' => 10]);

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertSee(route('login'), false)
        ->assertDontSee('wire:click="addToCart', false);
});

it('shows an add button to buyers and a stepper once the product is in the cart', function () {
    $product = Product::factory()->create(['stock' => 10]);
    $this->actingAs(cardBuyer());

    $this->blade('<x-mp.product-card :product="$product" :qty="0" />', ['product' => $product])
        ->assertSee('wire:click="addToCart('.$product->id.')"', false);

    $this->blade('<x-mp.product-card :product="$product" :qty="3" />', ['product' => $product])
        ->assertSee('wire:click="setCartQuantity('.$product->id.', 2)"', false)
        ->assertSee('wire:click="setCartQuantity('.$product->id.', 4)"', false);
});

it('shows sold out when stock is below the minimum purchase', function () {
    $product = Product::factory()->create(['stock' => 1, 'min_purchase' => 2]);
    $this->actingAs(cardBuyer());

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertSee('Stok habis')
        ->assertDontSee('wire:click="addToCart', false);
});

it('renders a placeholder when the product has no photo and no category', function () {
    $product = Product::factory()->create(['category_id' => Category::factory(), 'photo_path' => null]);
    $product->setRelation('category', null);

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertSee('ph-icon', false)
        ->assertSee('--c: #F0B90B', false);
});

it('never renders the wholesale price', function () {
    $product = Product::factory()->create(['price' => 10000, 'wholesale_price' => 77777]);

    $this->blade('<x-mp.product-card :product="$product" />', ['product' => $product])
        ->assertDontSee('77.777')
        ->assertDontSee('77777');
});
