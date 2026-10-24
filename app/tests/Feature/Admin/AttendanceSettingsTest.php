<?php

use App\Enums\Role;
use App\Livewire\Admin\AttendanceSettings\Edit;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceLocation;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Sari']);
});

it('saves the rules', function () {
    Livewire::actingAs($this->admin)->test(Edit::class)
        ->assertDontSee('Toleransi terlambat')->set('maxAkurasi', 80)
        ->call('saveRules')->assertHasNoErrors();

    expect(AttendanceSetting::current()->max_akurasi_m)->toBe(80);
});

it('saves the general schedule, rejecting a day with only one time or pulang before masuk', function () {
    $c = Livewire::actingAs($this->admin)->test(Edit::class);

    $c->set('umum.2.masuk', '')->call('saveGeneral')->assertHasErrors('umum.2');
    $c->set('umum.2.masuk', '18:00')->call('saveGeneral')->assertHasErrors('umum.2');
    $c->set('umum.2.masuk', '07:30')->set('umum.6.libur', true)->call('saveGeneral')->assertHasNoErrors();

    expect(substr(AttendanceSchedule::whereNull('user_id')->where('weekday', 2)->value('jam_masuk'), 0, 5))->toBe('07:30')
        ->and(AttendanceSchedule::whereNull('user_id')->where('weekday', 6)->value('jam_masuk'))->toBeNull();
});

it('creates and deletes a per-sales schedule (all 7 rows)', function () {
    Livewire::actingAs($this->admin)->test(Edit::class)
        ->call('editOverride')->set('overrideUserId', $this->sales->id)
        ->set('khusus.1.masuk', '09:00')->call('saveOverride')->assertHasNoErrors()
        ->assertSee('Sari');

    expect(AttendanceSchedule::where('user_id', $this->sales->id)->count())->toBe(7);

    Livewire::actingAs($this->admin)->test(Edit::class)->call('deleteOverride', $this->sales->id);
    expect(AttendanceSchedule::where('user_id', $this->sales->id)->count())->toBe(0);
});

it('adds and deletes a holiday', function () {
    $c = Livewire::actingAs($this->admin)->test(Edit::class)
        ->set('holidayDate', '2026-12-25')->set('holidayName', 'Natal')->call('addHoliday')->assertHasNoErrors();
    $id = AttendanceHoliday::sole()->id;
    $c->call('deleteHoliday', $id);
    expect(AttendanceHoliday::count())->toBe(0);
});

it('saves a location for selected sales, enforces radius bounds, and warns about sales with no location', function () {
    Livewire::actingAs($this->admin)->test(Edit::class)->set('tab', 'lokasi')
        ->assertSee('Sari')   // warning: no location yet
        ->call('newLocation')
        ->set('locNama', 'Gudang')->set('lat', -6.2)->set('lng', 106.8)->set('radius', 0)
        ->call('saveLocation')->assertHasErrors('radius')
        ->set('radius', 1)->set('locSalesIds', [$this->sales->id])
        ->call('saveLocation')->assertHasNoErrors();

    $loc = AttendanceLocation::sole();
    expect($loc->radius_m)->toBe(1)->and($loc->users->pluck('id')->all())->toBe([$this->sales->id]);
    expect(AttendanceLocation::allowedFor($this->sales))->toHaveCount(1);
});

it('is admin-only', function () {
    $this->actingAs($this->sales)->get('http://'.config('app.admin_domain').'/pengaturan/absensi')->assertForbidden();
});

it('keeps every save button in the settings disabled until its own form changes', function () {
    Livewire::actingAs($this->admin)->test(Edit::class)
        ->assertSeeHtml('wire:target="umum"')
        ->set('tab', 'aturan')->assertSeeHtml('wire:target="maxAkurasi"')
        ->set('tab', 'libur')->assertSeeHtml('wire:target="holidayDate,holidayName"')
        ->set('tab', 'lokasi')->call('newLocation')->assertSeeHtml('wire:target="locNama,lat,lng,radius,locSalesIds,locActive"');
});
