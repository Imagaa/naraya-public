<?php

use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use App\View\Components\Mp\SalesContact;

it('normalises sales phone numbers into wa.me digits', function (?string $in, ?string $out) {
    expect(SalesContact::waNumber($in))->toBe($out);
})->with([
    ['0812-8841-2207', '6281288412207'],
    ['+62 812 8841 2207', '6281288412207'],
    ['6281288412207', '6281288412207'],
    ['812 8841 2207', '6281288412207'],
    ['', null],
    [null, null],
    ['-', null],
]);

it('renders the sales contact card with call and WhatsApp links', function () {
    $sales = User::factory()->create(['name' => 'Budi Santoso']);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'phone' => '0812-8841-2207']);
    $buyer = User::factory()->create();
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'sales_user_id' => $sales->id]);

    $this->actingAs($buyer);

    $this->blade('<x-mp.sales-contact />')
        ->assertSee('Budi Santoso')
        ->assertSee('href="tel:081288412207"', false)
        ->assertSee('href="https://wa.me/6281288412207"', false);
});

it('renders nothing when the buyer has no sales rep', function () {
    $buyer = User::factory()->create();
    BuyerProfile::factory()->create(['user_id' => $buyer->id, 'sales_user_id' => null]);

    $this->actingAs($buyer);

    expect(trim((string) $this->blade('<x-mp.sales-contact />')))->toBe('');
});

it('renders a status pill with icon and label', function () {
    $this->blade('<x-mp.status tone="info" icon="truck">Dikirim</x-mp.status>')
        ->assertSee('status status-info', false)
        ->assertSee('Dikirim')
        ->assertSee('<svg', false);
});

it('renders an empty state with icon, title and text', function () {
    $this->blade('<x-mp.empty icon="gift" title="Belum ada hadiah." text="Cek lagi nanti." />')
        ->assertSee('class="empty"', false)
        ->assertSee('Belum ada hadiah.')
        ->assertSee('Cek lagi nanti.')
        ->assertSee('<svg', false);
});

it('renders checkout steps with the current step marked', function () {
    $this->blade('<x-mp.steps :current="2" />')
        ->assertSeeInOrder(['Keranjang', 'Pengiriman', 'Pembayaran'])
        ->assertSee('aria-current="step"', false);
});

it('shows the task bar instead of search when the header has a title', function () {
    $this->actingAs(User::factory()->create());

    $this->blade('<x-mp.header title="Keranjang" subtitle="4 produk" back="/pesanan" :tabbar="false" />')
        ->assertSee('class="taskbar"', false)
        ->assertSee('Keranjang')
        ->assertSee('href="/pesanan"', false)
        ->assertDontSee('class="tabbar"', false);
});

it('loads the map bundle only when the layout asks for maps', function () {
    $layout = file_get_contents(resource_path('views/layouts/marketplace.blade.php'));

    expect($layout)->toContain('resources/js/pinpoint-map.js')->toContain('$maps');
});
