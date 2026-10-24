<?php

use App\Enums\Role;
use App\Livewire\Admin\Notifications;
use App\Models\User;
use Livewire\Livewire;

// Admin PWA
beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->host = 'http://admin.'.config('app.domain');
});

it('ships an installable admin manifest with its icons', function () {
    $manifest = json_decode(file_get_contents(public_path('admin-manifest.json')), true);

    expect($manifest)->name->toBe('Naraya Admin')->display->toBe('standalone')->scope->toBe('/')
        ->and(collect($manifest['icons'])->pluck('src')->all())->toContain('/icons/admin-192.png', '/icons/admin-512.png', '/icons/admin-maskable-512.png');
    foreach ($manifest['icons'] as $icon) {
        expect(file_exists(public_path(ltrim($icon['src'], '/'))))->toBeTrue();
    }
    expect(file_get_contents(public_path('admin-sw.js')))->toContain("addEventListener('push'");
});

it('links the manifest and the iPhone home-screen tags in the admin layout', function () {
    $this->actingAs($this->admin)->get($this->host.'/')->assertOk()
        ->assertSee('<link rel="manifest" href="/admin-manifest.json">', false)
        ->assertSee('apple-touch-icon', false)
        ->assertSee('apple-mobile-web-app-capable', false)
        ->assertSee('Pasang aplikasi admin di HP');
});

it('shows the install steps for the device that opens /pasang', function (string $agent, string $see, string $dontSee) {
    $this->actingAs($this->admin)->withHeader('User-Agent', $agent)->get($this->host.'/pasang')->assertOk()
        ->assertSee($see)->assertDontSee($dontSee);
})->with([
    'android chrome' => ['Mozilla/5.0 (Linux; Android 14; SM-A145F) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Mobile Safari/537.36', 'Pasang aplikasi', 'Tambahkan ke Layar Utama'],
    'iphone safari' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/18.0 Mobile/15E148 Safari/604.1', 'Tambahkan ke Layar Utama', 'Buka di Safari'],
    'iphone chrome' => ['Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) CriOS/129.0 Mobile/15E148 Safari/604.1', 'Buka di Safari', 'Tambahkan ke Layar Utama'],
    'desktop' => ['Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/129.0 Safari/537.36', 'Buka alamat ini di HP', 'Tambahkan ke Layar Utama'],
]);

it('sends a test notification to the admin who asks, only', function () {
    $other = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($this->admin)->test(Notifications::class)->call('sendTest');

    expect($this->admin->notifications()->where('data->title', 'Notifikasi uji')->count())->toBe(1)
        ->and($other->notifications()->count())->toBe(0);
});
