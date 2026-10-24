<?php

use App\Enums\Role;
use App\Models\User;

it('puts the naraya logo in the marketplace header, the grosir/toys lockups in the storefront tabs, and the kit favicons', function () {
    $this->get('http://naraya.test/')
        ->assertSee('/brand/naraya-horizontal.svg', false)
        ->assertSee('/brand/naraya-grosir-horizontal.svg', false)
        ->assertSee('/brand/naraya-toys-horizontal.svg', false)
        ->assertSee('/brand/naraya-horizontal-reversed.svg', false)   // footer, dark
        ->assertSee('/brand/naraya-investasi-horizontal.svg', false)  // investment card
        ->assertSee('href="/favicon.svg"', false)
        ->assertDontSee('logo-mark');
});

it('uses the reversed lockup on the admin sidebar and logos in the line switch', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/')
        ->assertSee('/brand/naraya-horizontal-reversed.svg', false)
        ->assertSee('/brand/naraya-toys-horizontal.svg', false);
});

it('uses line symbols in the sales switch', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $this->actingAs($sales)->get(salesUrl('/'))
        ->assertSee('/brand/naraya-horizontal.svg', false)
        ->assertSee('/brand/naraya-grosir-symbol.svg', false)
        ->assertSee('/brand/naraya-toys-symbol.svg', false);
});

it('ships every logo file the app points at', function () {
    foreach (['naraya-horizontal', 'naraya-horizontal-reversed', 'naraya-grosir-horizontal', 'naraya-toys-horizontal', 'naraya-investasi-horizontal',
        'naraya-grosir-symbol', 'naraya-toys-symbol', 'naraya-grosir-horizontal-black', 'naraya-toys-horizontal-black'] as $name) {
        expect(public_path("brand/{$name}.svg"))->toBeFile();
    }
    expect(public_path('brand/png/naraya-grosir-horizontal-black.png'))->toBeFile()
        ->and(public_path('favicon.svg'))->toBeFile()
        ->and(public_path('apple-touch-icon.png'))->toBeFile();
});
