<?php

use App\Enums\Role;
use App\Livewire\Marketplace\Orders;
use App\Livewire\Marketplace\Orders\Show;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Support\Cart;
use Livewire\Livewire;

beforeEach(function () {
    $this->buyer = User::factory()->create(['role' => Role::User]);
    $this->actingAs($this->buyer);
});

function myOrder(string $status, array $attrs = []): Order
{
    return Order::factory()->create(['buyer_id' => auth()->id(), 'status' => $status] + $attrs);
}

it('lists orders as cards with status, thumbnails and progress', function () {
    $o = myOrder('processing');
    OrderItem::factory()->for($o)->create(['product_name' => 'Aqua Botol 600 ml']);

    $this->get(route('orders.index'))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('Pesanan #'.$o->id)
        ->assertSee('status status-brand', false)
        ->assertSee('Disiapkan')
        ->assertSee('Aqua Botol 600 ml')
        ->assertSee('class="o-thumbs"', false)
        ->assertDontSee('<table', false);
});

it('counts and filters cancelled and rejected orders together', function () {
    myOrder('cancelled');
    myOrder('rejected_by_user');
    myOrder('completed');

    Livewire::test(Orders::class)
        ->assertViewHas('statusCounts', fn ($c) => $c['cancelled'] === 2 && $c['all'] === 3)
        ->call('setFilter', 'cancelled')
        ->assertViewHas('orders', fn ($p) => $p->total() === 2);
});

it('confirms a delivered order from the list', function () {
    $o = myOrder('shipped', ['sales_delivered_at' => now()]);

    Livewire::test(Orders::class)
        ->assertSee('Konfirmasi Diterima')
        ->call('confirmReceived', $o->id);

    expect($o->fresh()->buyer_confirmed_at)->not->toBeNull();
});

it('refuses to confirm or reorder another buyer\'s order', function (string $method) {
    $other = Order::factory()->create(['status' => 'completed']);

    Livewire::test(Orders::class)->call($method, $other->id)->assertForbidden();
})->with(['confirmReceived', 'reorder']);

it('reorders a completed order into the cart and goes to checkout', function () {
    $p = Product::factory()->create(['stock' => 20, 'is_active' => true]);
    $o = myOrder('completed');
    OrderItem::factory()->for($o)->create(['product_id' => $p->id, 'quantity' => 2]);

    Livewire::test(Orders::class)
        ->call('reorder', $o->id)
        ->assertRedirect(route('checkout'));

    expect(Cart::quantities())->toBe([$p->id => 2])
        ->and(session('status'))->toBe('1 produk ditambahkan ke keranjang.');
});

it('stays put and explains when nothing in the order can be bought', function () {
    $p = Product::factory()->create(['stock' => 0, 'is_active' => true]);
    $o = myOrder('completed');
    OrderItem::factory()->for($o)->create(['product_id' => $p->id]);

    Livewire::test(Orders::class)
        ->call('reorder', $o->id)
        ->assertNoRedirect()
        ->assertSee('Produk di pesanan ini sedang tidak tersedia.');

    expect(Cart::count())->toBe(0);
});

it('shows the order detail with timeline, banner action and no emoji', function () {
    $o = myOrder('shipped', ['sales_delivered_at' => now(), 'gudang_handover_at' => now()->subDay()]);

    $this->get(route('orders.show', $o))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('class="taskbar"', false)
        ->assertSee('Perjalanan pesanan')
        ->assertSee('Disiapkan')
        ->assertSee('Konfirmasi Diterima')
        ->assertDontSee('✓');
});

it('reorders from the detail page', function () {
    $p = Product::factory()->create(['stock' => 20, 'is_active' => true]);
    $o = myOrder('completed');
    OrderItem::factory()->for($o)->create(['product_id' => $p->id, 'quantity' => 1]);

    Livewire::test(Show::class, ['order' => $o])
        ->call('reorder')
        ->assertRedirect(route('checkout'));
});

it('shortens month spending so it fits a phone stat tile', function (int $in, string $out) {
    expect(Orders::shortRupiah($in))->toBe($out);
})->with([[2940000, '2,9 jt'], [3000000, '3 jt'], [385000, '385 rb'], [900, '900'], [0, '0']]);

it('paginates with the marketplace pager, not the unstyled Tailwind one', function () {
    Order::factory()->count(11)->create(['buyer_id' => auth()->id(), 'status' => 'completed']);

    Livewire::test(Orders::class)
        ->assertSee('class="pager"', false)
        ->assertSeeHtml('<span class="num" title="Halaman 1 dari 2">1/2</span>')
        ->assertDontSee('w-5 h-5', false)
        ->call('nextPage')
        ->assertSeeHtml('<span class="num" title="Halaman 2 dari 2">2/2</span>');
});

it('gives each order card one primary action for its state, with Detail as the secondary', function () {
    myOrder('shipped', ['sales_delivered_at' => now()]);
    myOrder('cancelled');
    myOrder('processing');

    $html = Livewire::test(Orders::class)->html();
    expect(substr_count($html, 'Konfirmasi Diterima'))->toBe(1)
        ->and(substr_count($html, 'Pesan Lagi'))->toBe(1)
        ->and(substr_count($html, '>Detail<'))->toBe(3)
        ->and($html)->not->toContain('>Komplain<')
        ->and($html)->not->toContain('btn-ink');
});

it('puts the order detail primary action in a sticky bar on phones and makes Komplain a link', function () {
    $order = myOrder('completed', ['completed_at' => now()]);

    Livewire::test(Show::class, ['order' => $order])
        ->assertSeeHtml('class="paybar"')
        ->assertSeeHtml('class="link complaint-link"');
});
