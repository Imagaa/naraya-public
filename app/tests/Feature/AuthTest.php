<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PortalLogin;
use App\Models\User;
use Livewire\Livewire;

it('logs a staff user in at their portal door and redirects them to their role subdomain', function () {
    $admin = User::factory()->create([
        'role' => Role::Admin,
        'password' => bcrypt('password'),
    ]);

    Livewire::test(PortalLogin::class, ['portal' => Role::Admin])
        ->set('email', $admin->email)
        ->set('password', 'password')
        ->call('authenticate')
        ->assertRedirect('http://admin.'.config('app.domain').'/');

    expect(auth()->guard('admin')->check())->toBeTrue();
});

it('rejects a wrong password', function () {
    $admin = User::factory()->create(['password' => bcrypt('password')]);

    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', $admin->email)
        ->set('password', 'wrong')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();
});
