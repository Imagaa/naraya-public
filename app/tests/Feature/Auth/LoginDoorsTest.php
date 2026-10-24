<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PortalLogin;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

function doorUser(Role $role, bool $active = true): User
{
    $user = User::factory()->create(['role' => $role, 'email' => $role->value.'-'.uniqid().'@t.test', 'password' => Hash::make('rahasia123')]);
    if ($role === Role::User) {
        BuyerProfile::factory()->create(['user_id' => $user->id, 'is_active' => $active]);
    }
    if ($role === Role::Sales) {
        SalesProfile::factory()->create(['user_id' => $user->id, 'is_active' => $active]);
    }

    return $user;
}

it('logs a buyer in at the buyer door', function () {
    $buyer = doorUser(Role::User);
    Livewire::test(Login::class, ['method' => 'email'])->set('email', $buyer->email)->set('password', 'rahasia123')->call('authenticate')
        ->assertRedirect('http://'.config('app.domain').'/');
    $this->assertAuthenticatedAs($buyer);
});

it('logs each staff role in at its own portal door and sends it to that portal', function (Role $role) {
    $user = doorUser($role);
    Livewire::test(PortalLogin::class, ['portal' => $role])->set('email', $user->email)->set('password', 'rahasia123')->call('authenticate')
        ->assertRedirect('http://'.$role->portalDomain().'/');
    $this->assertAuthenticatedAs($user, $role->value);
})->with([Role::Admin, Role::Sales]);

it('rejects every wrong door identically', function (array $door, Role $role, string $keyPrefix) {
    $user = doorUser($role);
    $remember = $user->remember_token;

    Livewire::test(...$door)->set('email', $user->email)->set('password', 'rahasia123')->call('authenticate')
        ->assertHasErrors(['email'])->assertSee('Email atau kata sandi salah.');

    foreach (['web', 'admin', 'sales'] as $guard) {
        $this->assertGuest($guard);
    }
    expect($user->fresh()->remember_token)->toBe($remember)
        ->and(RateLimiter::attempts($keyPrefix.strtolower($user->email).'|127.0.0.1'))->toBe(1);
})->with([
    'admin on buyer door' => [[Login::class, ['method' => 'email']], Role::Admin, ''],
    'sales on buyer door' => [[Login::class, ['method' => 'email']], Role::Sales, ''],
    'buyer on admin door' => [[PortalLogin::class, ['portal' => Role::Admin]], Role::User, 'admin|'],
    'sales on admin door' => [[PortalLogin::class, ['portal' => Role::Admin]], Role::Sales, 'admin|'],
    'admin on sales door' => [[PortalLogin::class, ['portal' => Role::Sales]], Role::Admin, 'sales|'],
]);

it('gives an unknown email and a wrong password the same error, and still hashes for the unknown email', function () {
    $buyer = doorUser(Role::User);
    Livewire::test(Login::class, ['method' => 'email'])->set('email', $buyer->email)->set('password', 'salah')->call('authenticate')
        ->assertHasErrors(['email'])->assertSee('Email atau kata sandi salah.');

    Hash::spy();
    Livewire::test(Login::class, ['method' => 'email'])->set('email', 'tidak-ada@t.test')->set('password', 'apa saja')->call('authenticate')
        ->assertHasErrors(['email'])->assertSee('Email atau kata sandi salah.');
    Hash::shouldHaveReceived('check')->once();
});

it('rejects a deactivated buyer or sales with the same error', function (array $door, Role $role, string $guard) {
    $user = doorUser($role, active: false);
    Livewire::test(...$door)->set('email', $user->email)->set('password', 'rahasia123')->call('authenticate')
        ->assertHasErrors(['email'])->assertSee('Email atau kata sandi salah.');
    $this->assertGuest($guard);
})->with([[[Login::class, ['method' => 'email']], Role::User, 'web'], [[PortalLogin::class, ['portal' => Role::Sales]], Role::Sales, 'sales']]);

it('sends guests on each staff portal to its own door and marketplace guests to the buyer login', function () {
    foreach ([Role::Admin, Role::Sales] as $role) {
        $this->get('http://'.$role->portalDomain().'/')->assertRedirect('http://'.$role->portalDomain().'/login');
    }
    $this->get('http://'.config('app.domain').'/pesanan')->assertRedirect('http://'.config('app.domain').'/login');
});

it('logs staff out to their portal door and buyers out to the home page', function () {
    $this->actingAs(doorUser(Role::Admin))->post('http://'.config('app.admin_domain').'/logout')
        ->assertRedirect('http://'.config('app.admin_domain').'/login');
    $this->actingAs(doorUser(Role::User))->post('http://'.config('app.domain').'/logout')->assertRedirect('/');
});

it('renders a portal door in the buyer-login look, and never links the staff side from the buyer login', function () {
    $this->get('http://'.config('app.sales_domain').'/login')->assertOk()
        ->assertSee('Masuk Sales')
        ->assertSee('class="auth"', false)
        ->assertSee('class="auth-brand"', false)
        ->assertSee('class="pw-toggle"', false)
        ->assertDontSee('Daftar sekarang');
    expect(file_get_contents(resource_path('views/layouts/staff-auth.blade.php')))
        ->toContain('resources/css/marketplace.css')->not->toContain('resources/css/app.css');
    $buyerLogin = $this->get('http://'.config('app.domain').'/login')->assertOk();
    foreach ([config('app.staff_domain'), config('app.admin_domain'), config('app.sales_domain')] as $host) {
        $buyerLogin->assertDontSee($host);
    }
});
