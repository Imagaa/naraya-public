<?php

use App\Enums\Role;
use App\Livewire\Auth\PortalLogin;
use App\Models\User;
use App\Support\DoorAuthenticator;
use Livewire\Livewire;

// Each staff portal (admin, sales) is its own login door with its own
// host-only session, so one browser can be signed in to several portals at once
// and signing in or out of one never touches another.

function portalUrl(Role $role, string $path = '/'): string
{
    return 'http://'.$role->portalDomain().$path;
}

dataset('portals', [Role::Admin, Role::Sales]);

it('sends a guest on a portal to that portal\'s own login', function (Role $role) {
    $this->get(portalUrl($role))->assertRedirect(portalUrl($role, '/login'));
    $this->get(portalUrl($role, '/login'))->assertOk()->assertSee('Masuk '.$role->label());
})->with('portals');

it('logs a role in only at its own door', function (Role $role) {
    $user = User::factory()->create(['role' => $role, 'password' => 'rahasia123']);

    Livewire::withHeaders(['Host' => $role->portalDomain()])
        ->test(PortalLogin::class, ['portal' => $role])
        ->set('email', $user->email)->set('password', 'rahasia123')
        ->call('authenticate')
        ->assertRedirect(portalUrl($role));
})->with('portals');

it('turns any other role away at a door with the one generic message', function () {
    $sales = User::factory()->create(['role' => Role::Sales, 'password' => 'rahasia123']);

    Livewire::test(PortalLogin::class, ['portal' => Role::Admin])
        ->set('email', $sales->email)->set('password', 'rahasia123')
        ->call('authenticate')
        ->assertHasErrors(['email' => DoorAuthenticator::FAILED]);
    expect(auth()->guard('admin')->check())->toBeFalse();
});

it('gives every portal its own host-only session cookie', function () {
    $marketplaceCookie = config('session.cookie');
    $names = [];
    foreach ([Role::Admin, Role::Sales] as $role) {
        $response = $this->get(portalUrl($role, '/login'));
        $cookie = collect($response->headers->getCookies())->first(fn ($c) => str_ends_with($c->getName(), '-'.$role->value));
        expect($cookie)->not->toBeNull()
            ->and($cookie->getDomain())->toBeNull();   // host-only: never sent to a sibling subdomain
        $names[] = $cookie->getName();
    }
    expect(array_unique($names))->toHaveCount(2)
        ->and($names)->not->toContain($marketplaceCookie);
});

it('keeps two portals signed in side by side and logs out of one only', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($admin, 'admin')->actingAs($sales, 'sales');
    $this->get(portalUrl(Role::Admin))->assertOk();
    $this->get(portalUrl(Role::Sales))->assertOk();

    $this->post(portalUrl(Role::Sales, '/logout'))->assertRedirect(portalUrl(Role::Sales, '/login'));
    expect(auth()->guard('sales')->check())->toBeFalse()
        ->and(auth()->guard('admin')->user()?->id)->toBe($admin->id);
});

it('turns the old shared staff address into a portal chooser', function () {
    foreach (['/', '/login'] as $path) {
        $this->get('http://'.config('app.staff_domain').$path)->assertOk()
            ->assertSee(portalUrl(Role::Admin, '/login'), false)
            ->assertSee(portalUrl(Role::Sales, '/login'), false)
            ->assertDontSee('Siapkan dan serahkan barang');
    }
});
