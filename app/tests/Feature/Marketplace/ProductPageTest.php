<?php

use App\Enums\Role;
use App\Livewire\Marketplace\ProductShow;
use App\Models\Category;
use App\Models\LoyaltySetting;
use App\Models\Product;
use App\Models\ProductPhoto;
use App\Models\User;
use App\Support\Cart;
use Livewire\Livewire;

function pageBuyer(): User
{
    return User::factory()->create(['role' => Role::User]);
}

it('shows an active product by slug to guests', function () {
    $category = Category::factory()->create(['name' => 'Mie Instan']);
    Product::factory()->create([
        'name' => 'Mie Gaga 100 Ayam Bawang', 'category_id' => $category->id,
        'price' => 109600, 'unit' => 'karton', 'pieces_per_unit' => 40, 'min_purchase' => 2,
        'stock' => 128 * 40, 'description' => 'Mie goreng rasa ayam bawang.', 'wholesale_price' => 77777, // stock is in pcs: 128 karton
    ]);

    $this->get('http://naraya.test/produk/mie-gaga-100-ayam-bawang')
        ->assertStatus(200)
        ->assertSee('Mie Gaga 100 Ayam Bawang')
        ->assertSee('Mie Instan')
        ->assertSee('Rp 109.600')
        ->assertSee('/karton')
        ->assertSee('≈ Rp 2.740 per pcs')
        ->assertSee('Minimal 2 · maksimal 128')
        ->assertSee('Mie goreng rasa ayam bawang.')
        ->assertSee('Spesifikasi')
        ->assertDontSee('77.777')
        ->assertDontSee('77777');
});

it('returns 404 for an inactive product or an unknown slug', function () {
    Product::factory()->create(['name' => 'Produk Lama', 'is_active' => false]);

    $this->get('http://naraya.test/produk/produk-lama')->assertStatus(404);
    $this->get('http://naraya.test/produk/tidak-ada')->assertStatus(404);
});

it('keeps the quantity between the minimum purchase and stock', function () {
    $product = Product::factory()->create(['min_purchase' => 2, 'stock' => 3, 'price' => 1000]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSet('quantity', 2)
        ->call('decrement')->assertSet('quantity', 2)
        ->call('increment')->assertSet('quantity', 3)
        ->call('increment')->assertSet('quantity', 3)
        ->assertSee('Rp 3.000');
});

it('sends guests to login when adding or buying', function () {
    $product = Product::factory()->create(['stock' => 10]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->call('addSelectedToCart')->assertRedirect(route('login'));

    Livewire::test(ProductShow::class, ['product' => $product])
        ->call('buyNow')->assertRedirect(route('login'));

    expect(Cart::quantities())->toBe([]);
});

it('adds the selected quantity, and Beli Sekarang goes to checkout', function () {
    $product = Product::factory()->create(['stock' => 10, 'min_purchase' => 2]);

    Livewire::actingAs(pageBuyer())
        ->test(ProductShow::class, ['product' => $product])
        ->call('increment')
        ->call('addSelectedToCart')
        ->assertSet('added', true);
    expect(Cart::quantities())->toBe([$product->id => 3]);

    Cart::clear();
    Livewire::actingAs(pageBuyer())
        ->test(ProductShow::class, ['product' => $product])
        ->call('buyNow')
        ->assertRedirect(route('checkout'));
    expect(Cart::quantities())->toBe([$product->id => 2]);
});

it('no longer promises points on the product page', function () {
    LoyaltySetting::first()?->update(['rupiah_per_point' => 10000]) ?? LoyaltySetting::create(['rupiah_per_point' => 10000]);
    $product = Product::factory()->create(['price' => 109600, 'min_purchase' => 2, 'stock' => 10]);

    Livewire::test(ProductShow::class, ['product' => $product])->assertDontSee('poin loyalti');
});

it('shows gallery thumbnails when the product has extra photos', function () {
    $product = Product::factory()->create(['photo_path' => 'products/cover.jpg']);
    ProductPhoto::create(['product_id' => $product->id, 'path' => 'products/extra.jpg', 'sort_order' => 1]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSee('class="thumbs"', false)
        ->assertSee('products/extra.jpg', false);
});

it('renders a placeholder when the product has no photo and no category', function () {
    $product = Product::factory()->create(['photo_path' => null]);
    $product->category()->dissociate()->save();

    Livewire::test(ProductShow::class, ['product' => $product->fresh()])
        ->assertSee('ph-icon', false)
        ->assertDontSee('class="thumbs"', false);
});

it('shows sold out and hides the buy buttons when stock is below the minimum', function () {
    $product = Product::factory()->create(['stock' => 1, 'min_purchase' => 2]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSee('Stok habis')
        ->assertDontSee('Beli Sekarang');
});

it('lists products bought together', function () {
    $category = Category::factory()->create();
    $product = Product::factory()->create(['category_id' => $category->id]);
    Product::factory()->create(['category_id' => $category->id, 'name' => 'Saudara Sekategori']);
    Product::factory()->count(2)->create(['category_id' => $category->id]); // a rail needs 3 products

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSee('Sering dibeli bersama')
        ->assertSee('Saudara Sekategori');
});

it('shows the promo end date in Jakarta time', function () {
    // 20:00 UTC on 5 Oct is 03:00 WIB on 6 Oct.
    $product = Product::factory()->create([
        'price' => 10000, 'discount_price' => 9000,
        'discount_ends_at' => \Illuminate\Support\Carbon::parse('2030-10-05 20:00', 'UTC'),
    ]);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSee('harga promo sampai 06/10/2030');
});

it('links WhatsApp sharing to the product URL', function () {
    $product = Product::factory()->create(['name' => 'Kopi Kapal Api']);

    Livewire::test(ProductShow::class, ['product' => $product])
        ->assertSee('https://wa.me/?text=', false)
        ->assertSee(rawurlencode($product->url()), false);
});
