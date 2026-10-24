<?php

use App\Enums\Role;
use App\Livewire\Admin\Areas\Index;
use App\Models\Area;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\SalesAreas;
use Livewire\Livewire;

// Areas come from the sales people's store pins, not from the admin (user, 2026-10-12).

it('draws each sales person their own area around their stores and follows the pins', function () {
    $budi = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $budi->id, 'area_id' => null]);

    $a = BuyerProfile::factory()->create(['sales_user_id' => $budi->id, 'latitude' => -6.20, 'longitude' => 106.80]);
    BuyerProfile::factory()->create(['sales_user_id' => $budi->id, 'latitude' => -6.22, 'longitude' => 106.84]);

    $area = $budi->salesProfile->fresh()->area;
    expect($area->name)->toBe('Area Budi')
        ->and($area->lat)->toEqualWithDelta(-6.21, 0.0001)
        ->and($area->lng)->toEqualWithDelta(106.82, 0.0001)
        ->and($area->radius_km)->toBe(3) // farthest pin ~2.5 km + 0.5 margin
        ->and(BuyerProfile::where('area_id', $area->id)->count())->toBe(2);

    $a->update(['latitude' => -6.30]); // a pin moves: the circle follows
    expect($area->fresh()->radius_km)->toBeGreaterThan(3);
});

it('drops the old admin-made areas nobody owns', function () {
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Ani']);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'area_id' => null]);
    Area::create(['name' => 'Jakarta Pusat', 'lat' => -6.18, 'lng' => 106.83, 'radius_km' => 5]);

    SalesAreas::refreshAll();

    expect(Area::pluck('name')->all())->toBe(['Area Ani']);
});

it('shows the areas to the admin with nothing to add or edit', function () {
    $sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    SalesProfile::factory()->create(['user_id' => $sales->id, 'area_id' => null]);
    BuyerProfile::factory()->create(['sales_user_id' => $sales->id, 'latitude' => -6.2, 'longitude' => 106.8]);

    Livewire::actingAs(User::factory()->create(['role' => Role::Admin]))->test(Index::class)
        ->assertSee('Area Budi')->assertDontSee('Atur lokasi')->assertDontSee('Nama area baru');
});
