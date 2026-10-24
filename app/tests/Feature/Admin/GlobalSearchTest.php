<?php

use App\Enums\Role;
use App\Livewire\Admin\GlobalSearch;
use App\Models\BuyerProfile;
use App\Models\Order;
use App\Models\Product;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

function search(string $term): array
{
    return Livewire::actingAs(test()->admin)->test(GlobalSearch::class)->set('term', $term)->instance()->results();
}

it('waits for two characters', function () {
    Product::factory()->create(['name' => 'Gula Pasir']);

    expect(search('G'))->toBe([]);
});

it('finds stores, products and sales by name, grouped by type', function () {
    $toko = User::factory()->create(['role' => Role::User, 'name' => 'Toko Sinar Jaya']);
    $produk = Product::factory()->create(['name' => 'Sinar Minyak 1L']);
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Sinarto']);

    $results = search('sinar');

    expect(array_keys($results))->toBe(['Toko', 'Produk', 'Sales'])
        ->and($results['Toko'][0]['url'])->toBe(route('admin.buyers.edit', $toko))
        ->and($results['Produk'][0]['url'])->toBe(route('admin.products.edit', $produk))
        ->and($results['Sales'][0]['url'])->toBe(route('admin.sales.edit', $sales));
});

it('finds an order by its exact number and by its buyer name', function () {
    $buyer = User::factory()->create(['role' => Role::User, 'name' => 'Warung Bu Tini']);
    $order = Order::factory()->create(['id' => 1234, 'buyer_id' => $buyer->id]); // ids under 10 are below the 2-character minimum

    expect(search((string) $order->id)['Order'][0]['url'])->toBe(route('admin.orders.show', $order));
    expect(search('Bu Tini')['Order'][0]['label'])->toContain('#'.$order->id);
});

it('finds a product by sku or barcode', function () {
    Product::factory()->create(['name' => 'Kopi', 'sku' => 'KOP-001', 'barcode' => '8991234567890']);

    expect(search('KOP-001')['Produk'])->toHaveCount(1);
    expect(search('899123456')['Produk'])->toHaveCount(1);
});

it('finds a store by a phone number typed with a leading 0', function () {
    $toko = User::factory()->create(['role' => Role::User, 'name' => 'Toko Mawar']);
    BuyerProfile::factory()->create(['user_id' => $toko->id, 'phone' => '6281234567890']);

    expect(search('0812-3456-7890')['Toko'][0]['url'])->toBe(route('admin.buyers.edit', $toko));
});

it('finds a sales by phone stored the local way', function () {
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Andi']);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'phone' => '081298765432']);

    expect(search('6281298765432')['Sales'][0]['url'])->toBe(route('admin.sales.edit', $sales));
});

it('jumps to the first hit on enter', function () {
    $produk = Product::factory()->create(['name' => 'Teh Celup']);

    Livewire::actingAs($this->admin)->test(GlobalSearch::class)
        ->set('term', 'Teh Celup')
        ->call('go')
        ->assertRedirect(route('admin.products.edit', $produk));
});

it('says so when nothing matches', function () {
    Livewire::actingAs($this->admin)->test(GlobalSearch::class)
        ->set('term', 'zzzz')
        ->assertSee('Tidak ada hasil untuk');
});

it('is mounted once in the admin layout', function () {
    $this->actingAs($this->admin)->get(route('admin.dashboard'))->assertSee('class="adm-search"', false);
});

it('keeps the search dialog open while results re-render', function () {
    // Livewire's morph closes an open <dialog> whose new HTML lacks `open`; ignore.self keeps its own attributes.
    Livewire::actingAs($this->admin)->test(GlobalSearch::class)
        ->assertSeeHtml('<dialog x-ref="dialog" class="adm-search" wire:ignore.self');
});
