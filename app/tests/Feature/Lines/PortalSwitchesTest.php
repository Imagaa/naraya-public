<?php

use App\Enums\Role;
use App\Models\Order;
use App\Models\User;

// The marketplace is on hold behind a
// config switch (phpunit.xml turns both on so the frozen flows keep their own tests).

it('shows the on-hold page on every marketplace page while the marketplace is closed', function () {
    config(['naraya.marketplace_open' => false]);

    foreach (['/', '/produk', '/login', '/daftar', '/pesanan', '/akun'] as $path) {
        $this->get('http://'.config('app.domain').$path)
            ->assertOk()
            ->assertSee('Pemesanan online ditutup sementara')
            ->assertHeader('X-Robots-Tag', 'noindex');
    }
});

it('keeps the Midtrans webhook working while the marketplace is closed', function () {
    config(['naraya.marketplace_open' => false]);

    $response = $this->postJson('http://'.config('app.domain').'/midtrans/webhook', []);

    expect($response->getContent())->not->toContain('Pemesanan online ditutup sementara');
});

it('serves the marketplace normally when it is open', function () {
    config(['naraya.marketplace_open' => true]);

    $this->get('http://'.config('app.domain').'/login')->assertOk()->assertDontSee('Pemesanan online ditutup sementara');
});


