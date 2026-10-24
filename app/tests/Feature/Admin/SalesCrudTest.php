<?php

use App\Enums\Role;
use App\Livewire\Admin\Sales\Form;
use App\Livewire\Admin\Sales\Index;
use App\Models\Area;
use App\Models\SalesProfile;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists sales with their area', function () {
    $area = Area::factory()->create(['name' => 'Bekasi Timur']);
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso']);
    SalesProfile::factory()->for($sales)->for($area)->create();

    Livewire::actingAs($this->admin)
        ->test(Index::class)
        ->assertSee('Budi Santoso')
        ->assertSee('Bekasi Timur');
});

it('creates a sales user with a profile', function () {
    Livewire::actingAs($this->admin)
        ->test(Form::class)
        ->set('name', 'Andi Wijaya')
        ->set('email', 'andi.wijaya@example.com')
        ->set('password', 'password123')
        ->set('phone', '081200001111')
        ->set('monthlyTarget', 40000000)
        ->call('save')
        ->assertRedirect(route('admin.sales.index'));

    $user = User::where('email', 'andi.wijaya@example.com')->firstOrFail();
    expect($user->role)->toBe(Role::Sales);
    expect($user->salesProfile->monthly_target)->toBe(40000000);
});

it('shows the sales distribution map with each area\'s covering sales', function () {
    $area = Area::factory()->create(['name' => 'Bekasi Timur', 'lat' => -6.2, 'lng' => 107.0, 'radius_km' => 8]);
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Santoso']);
    SalesProfile::factory()->for($sales)->for($area)->create();

    Livewire::actingAs($this->admin)
        ->test(\App\Livewire\Admin\Sales\Map::class)
        ->assertSee('Bekasi Timur')
        ->assertSee('Budi Santoso')
        ->assertSee('8 km');
});

it('blocks non-admin roles from the sales index', function () {
    $buyer = User::factory()->create(['role' => Role::User]);

    $this->actingAs($buyer)
        ->get('http://admin.'.config('app.domain').'/sales')
        ->assertStatus(403);
});
