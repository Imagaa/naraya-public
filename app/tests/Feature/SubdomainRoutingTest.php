<?php

use App\Enums\Role;
use App\Models\User;

it('serves each subdomain its own root route', function () {
    // Root domain content is asserted precisely in MarketplaceHomeTest —
    // here we only care that each subdomain routes correctly.
    $this->get('http://naraya.test/')->assertStatus(200);
    $this->get('http://admin.naraya.test/')->assertStatus(302); // redirects to login, unauthenticated
    $this->get('http://sales.naraya.test/')->assertStatus(302);
    $this->get('http://gudang.naraya.test/')->assertNotFound(); // the gudang portal was removed on 2026-10-23
});

it('blocks a buyer from the admin subdomain even when logged in', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer)
        ->get('http://admin.naraya.test/')
        ->assertStatus(403);
});

it('allows an admin into the admin subdomain', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get('http://admin.naraya.test/')
        ->assertStatus(200)
        ->assertSee('Beranda'); // the dashboard greets by time of day; the sidebar's home item is stable
});

it('blocks staff roles from the buyer-only marketplace routes', function () {
    foreach ([Role::Admin, Role::Sales, Role::Gudang] as $role) {
        $staff = User::factory()->create(['role' => $role]);

        $this->actingAs($staff)
            ->get('http://naraya.test/akun')
            ->assertStatus(403);
    }
});
