<?php

use App\Enums\Role;
use App\Models\Area;
use App\Models\Order;
use App\Models\SalesProfile;
use App\Models\User;
use App\Services\SalesAssigner;

// Jakarta Pusat-ish coordinates, with Bekasi placed further east.
const JAKARTA_PUSAT = [-6.1805, 106.8284];
const BEKASI = [-6.2383, 107.0000];

function makeAreaWithSales(string $name, array $point, int $radiusKm = 10): SalesProfile
{
    $area = Area::factory()->create(['name' => $name, 'lat' => $point[0], 'lng' => $point[1], 'radius_km' => $radiusKm]);
    $sales = User::factory()->create(['role' => Role::Sales]);

    return SalesProfile::factory()->for($sales)->for($area)->create();
}

it('assigns the nearest area\'s sales when under capacity', function () {
    $near = makeAreaWithSales('Jakarta Pusat', JAKARTA_PUSAT);
    makeAreaWithSales('Bekasi', BEKASI);

    $order = Order::factory()->create(['delivery_lat' => JAKARTA_PUSAT[0], 'delivery_lng' => JAKARTA_PUSAT[1]]);

    $assigned = (new SalesAssigner)->assign($order);

    expect($assigned->id)->toBe($near->user_id);
});

it('overflows to the next nearest area once the nearest sales hits the 3-hour limit', function () {
    $near = makeAreaWithSales('Jakarta Pusat', JAKARTA_PUSAT);
    $far = makeAreaWithSales('Bekasi', BEKASI);

    // Two orders already assigned to the near sales within the last 3 hours.
    Order::factory()->count(2)->create([
        'sales_id' => $near->user_id,
        'sales_assigned_at' => now()->subHour(),
    ]);

    $order = Order::factory()->create(['delivery_lat' => JAKARTA_PUSAT[0], 'delivery_lng' => JAKARTA_PUSAT[1]]);

    $assigned = (new SalesAssigner)->assign($order);

    expect($assigned->id)->toBe($far->user_id);
});

it('ignores assignments older than the 3-hour window when counting load', function () {
    $near = makeAreaWithSales('Jakarta Pusat', JAKARTA_PUSAT);

    Order::factory()->count(2)->create([
        'sales_id' => $near->user_id,
        'sales_assigned_at' => now()->subHours(4),
    ]);

    $order = Order::factory()->create(['delivery_lat' => JAKARTA_PUSAT[0], 'delivery_lng' => JAKARTA_PUSAT[1]]);

    $assigned = (new SalesAssigner)->assign($order);

    expect($assigned->id)->toBe($near->user_id);
});

it('falls back to the nearest area\'s sales when every nearby area is over capacity', function () {
    $near = makeAreaWithSales('Jakarta Pusat', JAKARTA_PUSAT);
    $far = makeAreaWithSales('Bekasi', BEKASI);

    foreach ([$near, $far] as $profile) {
        Order::factory()->count(2)->create([
            'sales_id' => $profile->user_id,
            'sales_assigned_at' => now()->subMinutes(30),
        ]);
    }

    $order = Order::factory()->create(['delivery_lat' => JAKARTA_PUSAT[0], 'delivery_lng' => JAKARTA_PUSAT[1]]);

    $assigned = (new SalesAssigner)->assign($order);

    expect($assigned->id)->toBe($near->user_id);
});

it('returns null when the order has no pinpoint', function () {
    makeAreaWithSales('Jakarta Pusat', JAKARTA_PUSAT);

    $order = Order::factory()->create(['delivery_lat' => null, 'delivery_lng' => null]);

    expect((new SalesAssigner)->assign($order))->toBeNull();
});

it('returns null when no area has both a location and an active sales', function () {
    Area::factory()->create(['name' => 'Jakarta Pusat', 'lat' => JAKARTA_PUSAT[0], 'lng' => JAKARTA_PUSAT[1]]);

    $order = Order::factory()->create(['delivery_lat' => JAKARTA_PUSAT[0], 'delivery_lng' => JAKARTA_PUSAT[1]]);

    expect((new SalesAssigner)->assign($order))->toBeNull();
});
