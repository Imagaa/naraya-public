<?php

use App\Enums\Role;
use App\Models\User;

it('logs a buyer out via the main domain and returns them to the homepage', function () {
    $user = User::factory()->create(['role' => Role::User]);

    $this->actingAs($user)
        ->post('http://naraya.test/logout')
        ->assertRedirect('http://naraya.test');

    expect(auth()->check())->toBeFalse();
});

it('logs an admin out via the admin domain and sends them back to that portal door', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->post('http://admin.naraya.test/logout')
        ->assertRedirect('http://admin.naraya.test/login');

    expect(auth()->check())->toBeFalse();
});

it('logs a sales user out via the sales domain and sends them back to that portal door', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->post('http://sales.naraya.test/logout')
        ->assertRedirect('http://sales.naraya.test/login');

    expect(auth()->check())->toBeFalse();
});


it('requires authentication to hit logout', function () {
    $this->post('http://naraya.test/logout')
        ->assertRedirect('http://naraya.test/login');
});
