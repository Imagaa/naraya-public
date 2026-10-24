<?php

use App\Enums\Role;
use App\Models\User;

it('shows the custom 404 page for a nonexistent admin order', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get('http://admin.'.config('app.domain').'/orders/999999')
        ->assertStatus(404)
        ->assertSee('Halaman tidak ditemukan');
});

it('shows the custom 403 page when a sales user hits an admin route', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/products')
        ->assertStatus(403)
        ->assertSee('tidak punya akses');
});

it('renders the custom 419 page markup directly', function () {
    $this->view('errors.419')->assertSee('Sesi Anda telah berakhir');
});

it('renders the custom 500 page markup directly', function () {
    $this->view('errors.500')->assertSee('Terjadi kesalahan');
});
