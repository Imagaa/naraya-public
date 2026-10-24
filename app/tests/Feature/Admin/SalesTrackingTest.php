<?php

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Livewire\Admin\Areas\Index as Areas;
use App\Livewire\Admin\Sales\Index as SalesIndex;
use App\Livewire\Admin\Tracking\Index as Tracking;
use App\Models\Attendance;
use App\Models\BuyerProfile;
use App\Models\SalesProfile;
use App\Models\User;
use App\Models\Visit;
use App\Support\Sales\Shift;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:00', 'Asia/Jakarta'));
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->aji = User::factory()->create(['role' => Role::Sales, 'name' => 'Aji Purwanto']);
    SalesProfile::factory()->create(['user_id' => $this->aji->id, 'area_id' => null]);
    $this->store = User::factory()->create(['role' => Role::User, 'name' => 'Intan cell']);
    BuyerProfile::factory()->create(['user_id' => $this->store->id, 'sales_user_id' => $this->aji->id, 'latitude' => -6.21, 'longitude' => 106.81]);
});

it('counts each sales person\'s stores on Sales & Area', function () {
    Livewire::actingAs($this->admin)->test(SalesIndex::class)
        ->assertViewHas('salesUsers', fn ($list) => $list->first()->assigned_stores_count === 1);
});

it('draws every sales person\'s stores and circle in their own colour', function () {
    Livewire::actingAs($this->admin)->test(Areas::class)
        ->assertViewHas('groups', fn ($g) => $g->first()['name'] === 'Aji Purwanto' && $g->first()['stores']->first()['name'] === 'Intan cell')
        ->assertSee('salesAreasMap', false);
});

it('follows a working sales person live: route, stores visited, last GPS', function () {
    $masuk = Attendance::factory()->create(['sales_id' => $this->aji->id, 'tipe' => AttendanceType::Masuk, 'created_at' => now(), 'latitude' => -6.2, 'longitude' => 106.8]);
    $this->travelTo(Carbon::parse('2026-10-05 09:30', 'Asia/Jakarta'));
    Shift::record($masuk, [
        ['lat' => -6.2, 'lng' => 106.8, 'acc' => 10, 't' => Carbon::parse('2026-10-05 08:30', 'Asia/Jakarta')->getTimestampMs()],
        ['lat' => -6.21, 'lng' => 106.81, 'acc' => 10, 't' => Carbon::parse('2026-10-05 09:20', 'Asia/Jakarta')->getTimestampMs()],
    ]);
    Visit::create(['sales_id' => $this->aji->id, 'buyer_id' => $this->store->id, 'notes' => 'Kunjungan titip jual', 'visited_at' => now()->subMinutes(15)]);

    Livewire::actingAs($this->admin)->test(Tracking::class)
        ->assertSee('Aji Purwanto')->assertSee('Sedang bekerja')->assertSee('GPS terakhir 09.20')->assertSee('Intan cell')
        ->assertSee('1 dari 1 sales sedang bekerja')->assertSee('Dibawa dari gudang')
        ->assertViewHas('map', fn ($m) => count($m['shifts'][0]['path']) === 2 && $m['shifts'][0]['stores'][0]['name'] === 'Intan cell' && $m['shifts'][0]['open'])
        ->call('pick', $this->aji->id)->assertSet('salesId', $this->aji->id)->assertDispatched('tracking-update');

    \Livewire\Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Dashboard\Index::class)
        ->assertSee('1 dari 1 sales sedang bekerja')->assertSee('Aji Purwanto sejak 08.00');
});
