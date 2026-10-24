<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Livewire\Marketplace\Home;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\Category;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Livewire\Livewire;

it('renders the redesigned marketplace home', function () {
    $category = Category::factory()->create(['name' => 'Mie Instan']);
    Product::factory()->create(['category_id' => $category->id]); // empty categories are hidden

    $this->get('http://naraya.test/')
        ->assertStatus(200)
        ->assertSee('Grosir, mainan anak, dan')
        ->assertSee('Kategori')
        ->assertSee('Mie Instan')
        ->assertDontSee('Promo Pekan Ini');
});

it('shows the promo section and a hero slide for discounted products', function () {
    // A rail needs at least 3 products.
    Product::factory()->create(['name' => 'Teh Pucuk Harum', 'price' => 76800, 'discount_price' => 72000, 'discount_ends_at' => now()->addDays(4)]);
    Product::factory()->count(2)->create(['price' => 50000, 'discount_price' => 45000, 'discount_ends_at' => now()->addDays(4)]);

    Livewire::test(Home::class)
        ->assertSee('Promo Pekan Ini')
        ->assertSee('Berlaku sampai')
        ->assertSee('Teh Pucuk Harum')
        ->assertSee('Lihat Produk');
});

it('shows Beli Lagi only to buyers with past orders', function () {
    $area = Area::factory()->create();
    $buyer = User::factory()->create(['role' => Role::User]);
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'area_id' => $area->id]);

    Livewire::actingAs($buyer)->test(Home::class)->assertDontSee('Beli Lagi');

    $product = Product::factory()->create(['name' => 'Minyak Sania 2 L']);
    $order = Order::factory()->create(['buyer_id' => $buyer->id, 'status' => OrderStatus::Completed, 'completed_at' => now()]);
    OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => 2]);
    // A rail needs at least 3 products.
    foreach (Product::factory()->count(2)->create() as $more) {
        OrderItem::factory()->create(['order_id' => $order->id, 'product_id' => $more->id, 'quantity' => 1]);
    }

    Livewire::actingAs($buyer)->test(Home::class)
        ->assertSee('Beli Lagi')
        ->assertSee('Minyak Sania 2 L');
});

it('does not show Beli Lagi or Pesan Ulang to guests', function () {
    Livewire::test(Home::class)->assertDontSee('Beli Lagi')->assertDontSee('Pesan Ulang');
});
