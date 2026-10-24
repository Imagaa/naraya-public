<?php

use App\Enums\Role;
use App\Models\User;

// The marketplace and the staff portals live on sibling subdomains of one
// SESSION_DOMAIN, so they must not share a login: a buyer signed in on the
// marketplace is a guest on the staff side, and vice versa.

it('treats a buyer signed in on the marketplace as a guest on the sales portal', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer, 'web')
        ->get(salesUrl('/'))
        ->assertRedirect(salesUrl('/login'));
});

it('shows the sales door to a buyer signed in on the marketplace', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer, 'web')
        ->get(salesUrl('/login'))
        ->assertOk()
        ->assertSee('Masuk Sales');
});

it('does not let a sales login leak onto the marketplace', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales, 'sales')
        ->get('http://'.config('app.domain').'/pesanan')
        ->assertRedirect('http://'.config('app.domain').'/login');
});

it('keeps a separate session cookie for the staff portals', function () {
    [$marketplaceCookie, $staffCookie] = [config('session.cookie'), config('session.staff_cookie').'-sales'];

    $staff = $this->get(salesUrl('/login'));
    $marketplace = $this->get('http://'.config('app.domain').'/login');

    $staff->assertCookie($staffCookie)->assertCookieMissing($marketplaceCookie);
    $marketplace->assertCookie($marketplaceCookie)->assertCookieMissing($staffCookie);
    expect($staffCookie)->not->toBe($marketplaceCookie);
});

it('scopes the marketplace cookies to the marketplace host too, so they never reach a staff portal', function () {
    config(['session.domain' => '.'.config('app.domain')]);   // production sets SESSION_DOMAIN

    $response = $this->get('http://'.config('app.domain').'/login');

    foreach ($response->headers->getCookies() as $cookie) {
        expect($cookie->getDomain())->toBeNull("{$cookie->getName()} must be host-only");
    }
});
