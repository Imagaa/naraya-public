<?php

use App\Enums\OrderStatus;
use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

beforeEach(fn () => $this->admin = User::factory()->create(['role' => Role::Admin]));

it('loads the admin bundle instead of the staff app.css', function () {
    expect(file_get_contents(resource_path('views/layouts/admin.blade.php')))
        ->toContain('resources/css/admin.css')
        ->not->toContain('resources/css/app.css');
});

it('renders the job-grouped sidebar around the page content', function () {
    $this->actingAs($this->admin);

    $html = view('layouts.admin', ['slot' => 'Isi Halaman Admin'])->render();

    expect($html)->toContain('Isi Halaman Admin');
    foreach (['Beranda', 'Pesanan', 'Retur &amp; Komplain', 'Toko', 'Setoran sales', 'Buku Kas', 'Produk &amp; Stok', 'Sales &amp; Area', 'Absensi', 'Penukaran Hadiah', 'Katalog Hadiah', 'Laporan', 'Pengaturan'] as $label) {
        expect($html)->toContain('data-nav="'.$label.'"');
    }
    foreach (['Penjualan', 'Keuangan', 'Gudang', 'Tim Sales', 'Loyalty'] as $group) {
        expect($html)->toContain('<div class="adm-nav-group-label">'.$group.'</div>');
    }
    expect($html)->toContain('action="'.route('admin.logout').'"');
});

it('badges each menu item with the sum of its work queues', function () {
    Order::factory()->count(2)->create(['status' => OrderStatus::New]);
    Order::factory()->create(['status' => OrderStatus::Processing, 'sales_id' => null]);
    $this->actingAs($this->admin);

    $html = view('layouts.admin', ['slot' => ''])->render();

    expect($html)->toMatch('/data-nav="Pesanan"[^>]*>.*?<span class="adm-nav-badge">3<\/span>/s');
    expect($html)->not->toMatch('/data-nav="Setoran sales"[^>]*>(?:(?!<\/a>).)*adm-nav-badge/s');
});

it('keeps Pengaturan active on all three settings pages, each with the shared tabs', function (string $route) {
    $this->actingAs($this->admin)->get(route($route))
        ->assertOk()
        ->assertSee('data-nav="Pengaturan" aria-current="page"', false)
        ->assertSee('class="adm-tabs"', false);
})->with(['admin.loyalty-settings.edit', 'admin.warehouse-settings.edit', 'admin.expense-categories.index', 'admin.cash-book-settings.edit']);

it('keeps the hidden attribute stronger than component display rules', function () {
    expect(file_get_contents(resource_path('css/admin/legacy-map.css')))->toContain('[hidden] { display: none !important; }');
});

it('lets the admin logout forget this device\'s push subscription, like the sales portal', function () {
    $admin = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]);
    $admin->updatePushSubscription('https://fcm.googleapis.com/fcm/send/admin-phone', 'k', 't', 'aes128gcm');
    $admin->updatePushSubscription('https://fcm.googleapis.com/fcm/send/admin-laptop', 'k', 't', 'aes128gcm');

    $html = $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/')->getContent();
    expect($html)->toContain('data-push-logout')->toContain('name="push_endpoint"');

    $this->actingAs($admin)
        ->post('http://'.config('app.admin_domain').'/logout', ['push_endpoint' => 'https://fcm.googleapis.com/fcm/send/admin-phone'])
        ->assertRedirect('http://'.config('app.admin_domain').'/login');
    expect($admin->pushSubscriptions()->pluck('endpoint')->all())->toBe(['https://fcm.googleapis.com/fcm/send/admin-laptop']);
});

it('serves the sidebar badge counts so the sidebar can refresh without a page load', function () {
    $admin = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]);
    \App\Models\Order::factory()->create(['status' => \App\Enums\OrderStatus::New]);

    $this->actingAs($admin)->getJson('http://'.config('app.admin_domain').'/nav-badges')
        ->assertOk()
        ->assertJson(\App\Support\Admin\AdminNav::badges())
        ->assertJsonPath('Pesanan', fn ($n) => $n >= 1);

    $sales = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Sales]);
    $this->actingAs($sales)->getJson('http://'.config('app.admin_domain').'/nav-badges')->assertForbidden();
});

it('highlights Pengaturan on every settings page, the attendance settings included', function () {
    $admin = \App\Models\User::factory()->create(['role' => \App\Enums\Role::Admin]);
    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/pengaturan/absensi')
        ->assertSeeHtml('data-nav="Pengaturan" aria-current="page"');
});
