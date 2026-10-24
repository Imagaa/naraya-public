<?php

use App\Enums\Role;
use App\Models\User;
use Livewire\Livewire;

it('renders the empty-state component with a custom message', function () {
    $html = view('components.ui.empty-state', ['message' => 'Belum ada produk.'])->render();

    expect($html)->toContain('Belum ada produk.');
});

it('shows an empty state on the admin products list when there are no products', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Products\Index::class)
        ->assertSee('Belum ada produk');
});

it('shows an empty state on the marketplace order history when the buyer has no orders', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    Livewire::actingAs($buyer)
        ->test(\App\Livewire\Marketplace\Orders::class)
        ->assertSee('Belum ada pesanan');
});

it('shows an empty state on a laporan report when there are no rows in range', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($admin)
        ->test(\App\Livewire\Admin\Reports\Penjualan::class)
        ->set('startDate', '2000-01-01')
        ->set('endDate', '2000-01-02')
        ->assertSee('Belum ada data');
});
