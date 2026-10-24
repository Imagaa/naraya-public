<?php

use App\Enums\Role;
use App\Livewire\Sales\Buyers\Show;
use App\Livewire\Sales\Routes\Index as RoutePage;
use App\Models\BuyerProfile;
use App\Models\DeliveryRoute;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Livewire\Livewire;

beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    $this->stores = collect([['Toko Barat', -6.20, 106.80], ['Toko Timur', -6.30, 106.90], ['Toko Tanpa Titik', null, null]])->map(function ($s) {
        $store = User::factory()->create(['role' => Role::User, 'name' => $s[0]]);
        BuyerProfile::factory()->create(['user_id' => $store->id, 'sales_user_id' => $this->sales->id, 'latitude' => $s[1], 'longitude' => $s[2]]);

        return $store;
    });
});

it('plans a route on its own Rute page: pick stores, order from the GPS position, reorder, start, open a store, finish', function () {
    [$west, $east, $none] = $this->stores->pluck('id')->all();

    $page = Livewire::actingAs($this->sales)->test(RoutePage::class)
        ->assertSee('Toko Barat')->assertSee('Tanpa titik — lengkapi lokasi toko')
        ->call('arrange')->assertHasErrors('route')
        ->call('selectAll')
        ->call('arrange', -6.31, 106.91)
        ->assertSet('order', [$east, $west, $none])
        ->call('move', 1, -1)
        ->assertSet('order', [$west, $east, $none])
        ->call('start');

    expect(DeliveryRoute::sole()->stops->pluck('buyer_id')->all())->toBe([$west, $east, $none]);
    $page->assertSee('Rute aktif · 0 dari 3 toko')->assertSee('Buka toko')->assertSee('dir_action=navigate', false)
        ->assertSee('routePlannerMap', false)
        ->call('open', 'toko', $west)->assertSet('sheet', 'toko')->assertSee('sales-sheet', false)
        ->call('close')->call('finish');
    expect(DeliveryRoute::sole()->status)->toBe('finished');

    $this->actingAs($this->sales)->get(salesUrl('/rute'))->assertOk()->assertSeeInOrder(['Kerja', 'Rute', 'Lainnya']);
});

it('numbers each store the same in the list and on its pin, and ticking a store does not rebuild the map', function () {
    $page = Livewire::actingAs($this->sales)->test(RoutePage::class);
    $key = fn () => preg_match('/route-map-([0-9a-f]{32})/', $page->html(), $m) ? $m[1] : null;
    $before = $key();

    $page->assertViewHas('mapPoints', fn ($points) => collect($points)->pluck('no', 'name')->all() === ['1. Toko Barat' => 1, '3. Toko Timur' => 3])
        ->assertSeeHtmlInOrder(['route-stop-no">1<', 'Toko Barat', 'route-stop-no">2<', 'Toko Tanpa Titik', 'route-stop-no">3<', 'Toko Timur'])
        ->assertSee('x-effect="mark($wire.selected)"', false)
        ->set('selected', [$this->stores[0]->id]);
    expect($key())->toBe($before);
});

// The day's route is planned on the Rute page; a closed store is recorded from its sheet. (tests/Feature/Attendance/CheckInStockTest.php); a
// closed store is recorded from its sheet.
it('closes a stop with a photo from the store sheet', function () {
    [$west] = $this->stores->pluck('id')->all();
    $route = app(\App\Support\Sales\DeliveryRoutes::class)->start($this->sales, [$west]);
    $stop = $route->stops->first();

    Livewire::actingAs($this->sales)->test(Show::class, ['buyer' => $west])
        ->assertSee('Toko tutup')->call('$set', 'closing', true)
        ->call('saveClose')->assertHasErrors('closedPhoto')
        ->set('closedPhoto', UploadedFile::fake()->image('tutup.jpg'))
        ->call('saveClose')->assertHasNoErrors()->assertDispatched('sheet-done');

    expect($stop->fresh()->status)->toBe('closed');
    Livewire::actingAs($this->sales)->test(Show::class, ['buyer' => $west])->assertDontSee('Toko tutup');
});

it('lists routes for admin with a route\'s stops', function () {
    [$west, $east] = $this->stores->pluck('id')->all();
    $route = app(\App\Support\Sales\DeliveryRoutes::class)->start($this->sales, [$west, $east]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    Livewire::actingAs($admin)->test(\App\Livewire\Admin\Routes\Index::class)
        ->assertSee($this->sales->name)->assertSee('Berjalan')
        ->set('routeId', $route->id)
        ->assertSeeInOrder(['Toko Barat', 'Toko Timur']);
    $this->actingAs($admin)->get('http://'.config('app.admin_domain').'/rute-sales')->assertOk()->assertSee('Rute sales');
});
