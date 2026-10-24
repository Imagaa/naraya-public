<?php

use App\Enums\Role;
use App\Livewire\Admin\WarehouseSettings\Edit;
use App\Models\User;
use App\Models\WarehouseSetting;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('prefills the current warehouse location', function () {
    WarehouseSetting::first()->update(['address' => 'Gudang Lama', 'lat' => -6.2, 'lng' => 106.8]);

    Livewire::actingAs($this->admin)
        ->test(Edit::class)
        ->assertSet('address', 'Gudang Lama')
        ->assertSet('lat', -6.2)
        ->assertSet('lng', 106.8);
});

it('updates the warehouse location', function () {
    Livewire::actingAs($this->admin)
        ->test(Edit::class)
        ->set('address', 'Gudang Baru')
        ->set('lat', -6.25)
        ->set('lng', 106.85)
        ->call('save');

    $warehouse = WarehouseSetting::first();
    expect($warehouse->address)->toBe('Gudang Baru');
    expect($warehouse->lat)->toBe(-6.25);
    expect($warehouse->lng)->toBe(106.85);
});

it('requires a pinpoint before saving', function () {
    Livewire::actingAs($this->admin)
        ->test(Edit::class)
        ->set('lat', null)
        ->set('lng', null)
        ->call('save')
        ->assertHasErrors('lat');
});

it('blocks non-admin roles from the warehouse settings page', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer)
        ->get('http://admin.'.config('app.domain').'/warehouse-settings')
        ->assertStatus(403);
});
