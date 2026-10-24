<?php

use App\Enums\Role;
use App\Livewire\Admin\Attendances\DayOffs as AdminDayOffs;
use App\Livewire\Sales\Attendances\Index as SalesAttendance;
use App\Models\AttendanceHoliday;
use App\Models\AttendanceSchedule;
use App\Models\SalesDayOff;
use App\Models\User;
use App\Support\AttendanceCalendar;
use App\Support\AttendanceReport;
use App\Support\DayOffs;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-14 02:00')); // Wednesday 09:00 WIB
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->dayOffs = app(DayOffs::class);
});

it('makes Sunday a working day like Saturday', function () {
    $sunday = AttendanceSchedule::whereNull('user_id')->where('weekday', 7)->first();
    $saturday = AttendanceSchedule::whereNull('user_id')->where('weekday', 6)->first();

    expect($sunday->jam_masuk)->toBe($saturday->jam_masuk)->not->toBeNull()
        ->and((new AttendanceCalendar)->for($this->sales, Carbon::parse('2026-10-18', 'Asia/Jakarta')))->not->toBeNull();
});

it('takes a day off from tomorrow on, once per Monday–Sunday week, and the calendar treats it as off', function () {
    expect(fn () => $this->dayOffs->request($this->sales, '2026-10-14'))->toThrow(ValidationException::class); // today

    $off = $this->dayOffs->request($this->sales, '2026-10-16', 'Acara keluarga');
    expect($off->date->format('Y-m-d'))->toBe('2026-10-16')
        ->and((new AttendanceCalendar)->for($this->sales, Carbon::parse('2026-10-16', 'Asia/Jakarta')))->toBeNull()
        ->and((new AttendanceCalendar)->dayState($this->sales, Carbon::parse('2026-10-16', 'Asia/Jakarta'), null))->toBe('libur');

    expect(fn () => $this->dayOffs->request($this->sales, '2026-10-18'))->toThrow(ValidationException::class); // same week
    expect($this->dayOffs->request($this->sales, '2026-10-19')->date->format('Y-m-d'))->toBe('2026-10-19'); // next week
});

it('refuses national holidays and lets the sales cancel before the day', function () {
    AttendanceHoliday::create(['tanggal' => '2026-10-17', 'nama' => 'Libur nasional']);
    expect(fn () => $this->dayOffs->request($this->sales, '2026-10-17'))->toThrow(ValidationException::class);

    $off = $this->dayOffs->request($this->sales, '2026-10-15');
    $this->dayOffs->cancel($off, $this->sales);
    expect($off->fresh()->cancelled_at)->not->toBeNull()
        ->and($this->dayOffs->request($this->sales, '2026-10-16')->exists)->toBeTrue(); // quota freed

    $this->travelTo(Carbon::parse('2026-10-16 02:00'));
    expect(fn () => $this->dayOffs->cancel(SalesDayOff::active()->sole(), $this->sales))->toThrow(ValidationException::class); // the day itself
});

it('lets the sales request and cancel from the Absensi page', function () {
    Livewire::actingAs($this->sales)->test(SalesAttendance::class)
        ->assertSee('Libur minggu ini')->assertSee('Belum dipakai')
        ->set('dayOffDate', '2026-10-17')->set('dayOffNote', 'Ke dokter')
        ->call('requestDayOff')->assertHasNoErrors()
        ->assertSee('Sudah dipakai')->assertSee('Ke dokter')
        ->call('cancelDayOff', SalesDayOff::sole()->id)
        ->assertSee('Belum dipakai');
});

it('lists day offs for admin, who can cancel one with a reason; the daily list marks it', function () {
    $off = $this->dayOffs->request($this->sales, '2026-10-15', 'Acara keluarga');

    Livewire::actingAs($this->admin)->test(AdminDayOffs::class)
        ->assertSee('Budi')->assertSee('Acara keluarga')
        ->call('startCancel', $off->id)->call('cancel')->assertHasErrors('cancelReason')
        ->set('cancelReason', 'Ada stok opname')->call('cancel')->assertHasNoErrors();
    expect($off->fresh())->cancelled_by->toBe($this->admin->id)->cancel_reason->toBe('Ada stok opname');

    $again = $this->dayOffs->request($this->sales, '2026-10-16');
    $this->travelTo(Carbon::parse('2026-10-16 03:00'));
    $row = AttendanceReport::day(Carbon::parse('2026-10-16', 'Asia/Jakarta'))->firstWhere('sales.id', $this->sales->id);
    expect($row['state'])->toBe('libur')->and($row['day_off']?->is($again))->toBeTrue();
});

it('shows on the daily Absensi page how many sales are off that day and who, plus the day offs already requested ahead', function () {
    $sari = User::factory()->create(['role' => Role::Sales, 'name' => 'Sari']);
    $this->dayOffs->request($this->sales, '2026-10-15', 'Acara keluarga');   // tomorrow
    $this->dayOffs->request($sari, '2026-10-17');

    Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Attendances\Index::class)
        ->assertSee('Akan libur')->assertSeeInOrder(['Kamis, 15 Okt', 'Budi', 'Sabtu, 17 Okt', 'Sari']);

    $this->travelTo(Carbon::parse('2026-10-15 03:00'));
    Livewire::actingAs($this->admin)->test(\App\Livewire\Admin\Attendances\Index::class)
        ->assertViewHas('counts', fn ($c) => $c['libur'] === 1)
        ->assertSee('1 sales')
        ->call('setFilter', 'libur')
        ->assertViewHas('rows', fn ($rows) => $rows->pluck('sales.name')->all() === ['Budi']);
});
