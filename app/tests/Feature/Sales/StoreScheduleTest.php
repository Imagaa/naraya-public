<?php

use App\Enums\Role;
use App\Livewire\Sales\Home;
use App\Livewire\Sales\Routes\Index as RoutePage;
use App\Livewire\Sales\Schedule;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\StoreSchedule;
use App\Models\User;
use App\Support\Sales\StoreSchedules;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

// A weekly store schedule made by the sales person; Kerja shows today's.
beforeEach(function () {
    $this->sales = User::factory()->create(['role' => Role::Sales]);
    SalesProfile::factory()->create(['user_id' => $this->sales->id]);
    $this->store = fn (string $name, ?User $sales = null) => tap(User::factory()->create(['role' => Role::User, 'name' => $name]),
        fn ($u) => BuyerProfile::factory()->create(['user_id' => $u->id, 'sales_user_id' => ($sales ?? $this->sales)->id, 'owner_name' => 'Pak '.$name]));
    Carbon::setTestNow(Carbon::parse('2026-10-14 09:00', 'Asia/Jakarta')); // a Wednesday (Rabu) in WIB
});

afterEach(fn () => Carbon::setTestNow());

it('saves the whole week in one go and only for my own stores', function () {
    [$a, $b] = [($this->store)('Toko A'), ($this->store)('Toko B')];
    $other = ($this->store)('Toko Lain', User::factory()->create(['role' => Role::Sales]));

    Livewire::actingAs($this->sales)->test(Schedule::class)
        ->call('save', [1 => [$a->id, $b->id], 3 => [(string) $b->id]])
        ->assertDispatched('sheet-done');
    expect(StoreSchedules::daysByStore($this->sales))->toBe([$a->id => [1], $b->id => [1, 3]]);

    Livewire::actingAs($this->sales)->test(Schedule::class)->call('save', [1 => [$a->id]]);
    expect(StoreSchedules::daysByStore($this->sales))->toBe([$a->id => [1]]); // a day saved empty is emptied

    Livewire::actingAs($this->sales)->test(Schedule::class)->call('save', [2 => [$other->id]])->assertHasErrors('jadwal');
    expect(StoreSchedule::where('buyer_id', $other->id)->exists())->toBeFalse();
});

it('shows only today\'s scheduled stores on Kerja, every store behind search and "Semua toko"', function () {
    [$today, $monday] = [($this->store)('Toko Rabu'), ($this->store)('Toko Senin')];
    app(StoreSchedules::class)->saveDay($this->sales, 3, [$today->id]);
    app(StoreSchedules::class)->saveDay($this->sales, 1, [$monday->id]);

    Livewire::actingAs($this->sales)->test(Home::class)
        ->assertViewHas('stores', fn ($s) => $s->pluck('name')->all() === ['Toko Rabu'])
        ->assertViewHas('all', fn ($s) => $s->pluck('days', 'name')->all() === ['Toko Rabu' => 'Rab', 'Toko Senin' => 'Sen'])
        ->assertSee('Toko hari ini · Rabu')->assertSee('Semua toko saya (2)')->assertSee('Atur jadwal')
        ->assertSee('storeSearch(', false);
});

it('says so when nothing is scheduled today', function () {
    ($this->store)('Toko A');

    Livewire::actingAs($this->sales)->test(Home::class)
        ->assertViewHas('stores', fn ($s) => $s->isEmpty())
        ->assertSee('Belum ada toko terjadwal hari Rabu');
});

it('drops a store from the schedule once it belongs to another sales person', function () {
    $a = ($this->store)('Toko A');
    app(StoreSchedules::class)->saveDay($this->sales, 3, [$a->id]);
    $a->buyerProfile->update(['sales_user_id' => User::factory()->create(['role' => Role::Sales])->id]);

    expect(StoreSchedules::storeIdsFor($this->sales, 3))->toBe([])->and(StoreSchedules::daysByStore($this->sales))->toBe([]);
});

it('pre-ticks today\'s scheduled stores when planning a route', function () {
    [$a, $b] = [($this->store)('Toko A'), ($this->store)('Toko B')];
    app(StoreSchedules::class)->saveDay($this->sales, 3, [$b->id]);

    Livewire::actingAs($this->sales)->test(RoutePage::class)->assertSet('selected', [$b->id]);
});

it('opens as a sheet on Kerja with today selected', function () {
    Livewire::actingAs($this->sales)->test(Home::class)->call('open', 'jadwal')->assertSet('sheet', 'jadwal')
        ->assertSeeLivewire(Schedule::class);
    Livewire::actingAs($this->sales)->test(Schedule::class)->assertSee('storeSchedule(', false)->assertSee(', 3)', false);
});
