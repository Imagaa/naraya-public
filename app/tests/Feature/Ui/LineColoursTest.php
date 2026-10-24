<?php

use App\Enums\BusinessLine;
use App\Enums\Role;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\ActiveLine;

// Colour per business line
it('marks admin, sales and marketplace pages with the active line', function () {
    config(['naraya.grosir_open' => true]);
    $admin = User::factory()->create(['role' => Role::Admin]);
    $sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'is_active' => true]);

    foreach ([BusinessLine::Toys, BusinessLine::Naraya] as $line) {
        session([ActiveLine::KEY => $line->value]);
        $this->actingAs($admin)->get('http://admin.'.config('app.domain').'/')->assertOk()->assertSee('data-line="'.$line->value.'"', false);
        $this->actingAs($sales)->get(salesUrl('/'))->assertOk()->assertSee('data-line="'.$line->value.'"', false);
    }
});

it('gives a guest on the marketplace the default storefront line', function () {
    $this->get('http://'.config('app.domain').'/')->assertOk()
        ->assertSee('data-line="'.ActiveLine::current()->value.'"', false);
});

it('wraps the sales and admin investment pages in inv-scope', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin)->get('http://admin.'.config('app.domain').'/investasi')->assertOk()->assertSee('inv-scope', false);
});
