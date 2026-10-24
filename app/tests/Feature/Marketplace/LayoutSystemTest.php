<?php

use App\Models\Category;
use App\Models\Product;
use App\Support\Catalog;

it('orders actions secondary then primary, and marks a lone action full width', function () {
    $this->blade('<x-mp.actions><x-slot:secondary><a class="btn btn-secondary">Detail</a></x-slot:secondary><x-slot:primary><button class="btn btn-primary">Konfirmasi</button></x-slot:primary></x-mp.actions>')
        ->assertSeeInOrder(['Detail', 'Konfirmasi'])->assertDontSee('is-single');

    $this->blade('<x-mp.actions><x-slot:primary><button class="btn btn-primary">Tukar</button></x-slot:primary></x-mp.actions>')
        ->assertSee('actions is-single', false);
});

it('renders a rail only with at least 3 products', function () {
    $this->blade('<x-mp.rail title="Rak Dua" :products="$p" key-prefix="t" />', ['p' => Product::factory()->count(2)->create()])
        ->assertDontSee('Rak Dua');

    $this->blade('<x-mp.rail title="Rak Tiga" more-url="/produk" :products="$p" key-prefix="t" />', ['p' => Product::factory()->count(3)->create()])
        ->assertSee('Rak Tiga')->assertSee('Lihat semua')->assertSee('class="rail hscroll"', false);
});

it('renders bands with the shared header', function () {
    $this->blade('<x-mp.section tone="tint" title="Promo" subtitle="Berlaku sampai 7/10" more-url="/produk">isi-band</x-mp.section>')
        ->assertSee('seg seg-tint', false)->assertSeeInOrder(['Promo', 'Berlaku sampai 7/10', 'Lihat semua', 'isi-band']);
});

it('shows the cart bar only when the cart has items', function () {
    $this->blade('<x-mp.cart-bar :count="0" :total="0" />')->assertDontSee('Lihat keranjang');
    $this->blade('<x-mp.cart-bar :count="3" :total="286000" />')->assertSee('3 barang')->assertSee('Rp 286.000')->assertSee('Lihat keranjang');
});

it('hides categories without active products', function () {
    $full = Category::factory()->create(['name' => 'Ada Isi']);
    Product::factory()->create(['category_id' => $full->id]);
    $inactiveOnly = Category::factory()->create(['name' => 'Isi Nonaktif']);
    Product::factory()->create(['category_id' => $inactiveOnly->id, 'is_active' => false]);
    Category::factory()->create(['name' => 'Kosong']);

    expect(Catalog::categories()->pluck('name'))->toContain('Ada Isi')->not->toContain('Isi Nonaktif')->not->toContain('Kosong');
});
