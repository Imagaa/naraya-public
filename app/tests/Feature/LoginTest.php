<?php

use App\Enums\Role;
use App\Livewire\Auth\Login;
use App\Livewire\Auth\PortalLogin;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Livewire\Livewire;

it('redirects each role to its own portal after login', function (Role $role, string $expectedHost) {
    $user = User::factory()->create(['role' => $role, 'password' => bcrypt('secret123')]);

    // Buyers use the marketplace door; each staff role uses its own portal door.
    Livewire::test(...($role === Role::User ? [Login::class, ['method' => 'email']] : [PortalLogin::class, ['portal' => $role]]))
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertRedirect("http://{$expectedHost}/");
})->with([
    'admin' => [Role::Admin, 'admin.naraya.test'],
    'sales' => [Role::Sales, 'sales.naraya.test'],
    'user' => [Role::User, 'naraya.test'],
]);

it('gives the same generic error for a wrong password as for an unknown email', function () {
    $user = User::factory()->create(['role' => Role::User, 'password' => bcrypt('secret123')]);

    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('authenticate')
        ->assertHasErrors(['email' => 'Email atau kata sandi salah.']);

    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', 'nobody@naraya.test')
        ->set('password', 'whatever')
        ->call('authenticate')
        ->assertHasErrors(['email' => 'Email atau kata sandi salah.']);
});

it('throttles repeated failed attempts against one email without touching other accounts on the same IP', function () {
    $victim = User::factory()->create(['role' => Role::User, 'password' => bcrypt('secret123')]);
    $bystander = User::factory()->create(['role' => Role::User, 'password' => bcrypt('secret123')]);

    for ($i = 0; $i < 5; $i++) {
        Livewire::test(Login::class, ['method' => 'email'])
            ->set('email', $victim->email)
            ->set('password', 'wrong-password')
            ->call('authenticate');
    }

    // 6th attempt against the victim's account is throttled...
    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', $victim->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertHasErrors('email');

    expect(auth()->check())->toBeFalse();

    // ...but the bystander sharing the same test-client IP can still log in.
    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', $bystander->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertRedirect('http://naraya.test/');
});

it('clears the throttle counter on a successful login', function () {
    $user = User::factory()->create(['role' => Role::User, 'password' => bcrypt('secret123')]);

    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', $user->email)
        ->set('password', 'wrong-password')
        ->call('authenticate');

    Livewire::test(Login::class, ['method' => 'email'])
        ->set('email', $user->email)
        ->set('password', 'secret123')
        ->call('authenticate')
        ->assertRedirect('http://naraya.test/');

    expect(RateLimiter::tooManyAttempts(Str::transliterate(Str::lower($user->email).'|127.0.0.1'), 5))->toBeFalse();
});

it('redirects an already-authenticated user away from the login page instead of showing it', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);

    $this->actingAs($admin)
        ->get('http://naraya.test/login')
        ->assertRedirect('http://admin.naraya.test/');
});

it('renders one responsive login page in the marketplace layout', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('<body class="mp" data-line="', false)
        ->assertSee('Ingat saya')
        ->assertDontSee('mp-desktop-only', false)
        ->assertDontSee('mp-mobile-only', false);
});

it('sets the remember token when Ingat saya is ticked', function () {
    $user = \App\Models\User::factory()->create(['password' => bcrypt('rahasia123'), 'role' => \App\Enums\Role::User, 'remember_token' => null]);

    \Livewire\Livewire::test(\App\Livewire\Auth\Login::class, ['method' => 'email'])
        ->set('email', $user->email)
        ->set('password', 'rahasia123')
        ->set('remember', true)
        ->call('authenticate');

    expect($user->fresh()->remember_token)->not->toBeNull();
});
