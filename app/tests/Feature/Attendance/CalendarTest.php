<?php

use App\Enums\AttendanceStatus;
use App\Models\AttendanceSchedule;
use App\Models\AttendanceSetting;
use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\User;
use App\Support\AttendanceCalendar;
use Illuminate\Support\Carbon;

it('seeds the general schedule (Mon–Sun 08:00–17:00; Sunday became a working day with weekly day offs) and the settings row', function () {
    $general = AttendanceSchedule::whereNull('user_id')->orderBy('weekday')->get();

    expect($general)->toHaveCount(7)
        ->and(substr($general[0]->jam_masuk, 0, 5))->toBe('08:00')
        ->and(substr($general[5]->jam_pulang, 0, 5))->toBe('17:00')
        ->and(substr($general[6]->jam_masuk, 0, 5))->toBe('08:00')
        ->and(AttendanceSetting::current()->toleransi_menit)->toBe(10)
        ->and(AttendanceStatus::Terlambat->text(12))->toBe('Terlambat 12 mnt');
});

function wib(string $at): Carbon
{
    return Carbon::parse($at, 'Asia/Jakarta');
}

it('resolves the general schedule, a weekly off day and a holiday date', function () {
    sundayOff(); // these tests exercise an off day in the schedule; since 2026-10-10 Sunday is a working day by default
    $sales = User::factory()->create(['role' => Role::Sales]);
    AttendanceHoliday::create(['tanggal' => '2026-10-07', 'nama' => 'Libur Kantor']);
    $cal = new AttendanceCalendar;

    $monday = $cal->for($sales, wib('2026-10-05'));
    expect($monday['masuk']->format('Y-m-d H:i'))->toBe('2026-10-05 08:00')
        ->and($monday['pulang']->format('H:i'))->toBe('17:00')
        ->and($cal->for($sales, wib('2026-10-04')))->toBeNull()   // Sunday
        ->and($cal->for($sales, wib('2026-10-07')))->toBeNull()   // holiday
        ->and($cal->holidayOn(wib('2026-10-07')))->toBe('Libur Kantor');
});

it('uses a per-sales schedule entirely instead of the general one', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    foreach (range(1, 7) as $d) {
        AttendanceSchedule::create(['user_id' => $sales->id, 'weekday' => $d, 'jam_masuk' => $d === 1 ? null : '09:00', 'jam_pulang' => $d === 1 ? null : '18:00']);
    }
    $cal = new AttendanceCalendar;

    expect($cal->for($sales, wib('2026-10-05')))->toBeNull()                       // Monday off for this sales
        ->and($cal->for($sales, wib('2026-10-04'))['masuk']->format('H:i'))->toBe('09:00'); // Sunday works
});

it('records any check-in or check-out time on a working day as Tercatat (no set hours since 2026-10-12)', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $cal = new AttendanceCalendar;
    $tercatat = ['jadwal_jam' => null, 'selisih_menit' => null, 'status' => AttendanceStatus::Tercatat];

    expect($cal->judge($sales, AttendanceType::Masuk, wib('2026-10-05 06:30')->utc()))->toBe($tercatat)
        ->and($cal->judge($sales, AttendanceType::Masuk, wib('2026-10-05 13:45')))->toBe($tercatat)
        ->and($cal->judge($sales, AttendanceType::Pulang, wib('2026-10-05 15:00')))->toBe($tercatat);
});

it('still marks a check-in on an off day as Hari libur', function () {
    sundayOff();
    $sales = User::factory()->create(['role' => Role::Sales]);

    expect((new AttendanceCalendar)->judge($sales, AttendanceType::Masuk, wib('2026-10-04 09:00')))
        ->toBe(['jadwal_jam' => null, 'selisih_menit' => null, 'status' => AttendanceStatus::TanpaJadwal]);
});

it('derives the day state: belum all day, tidak_absen once the day is over, libur on off days', function () {
    sundayOff(); // these tests exercise an off day in the schedule; since 2026-10-10 Sunday is a working day by default
    $sales = User::factory()->create(['role' => Role::Sales]);
    $late = Attendance::factory()->create(['sales_id' => $sales->id, 'status' => AttendanceStatus::Terlambat]);

    $this->travelTo(wib('2026-10-05 22:30'));
    $cal = new AttendanceCalendar;
    expect($cal->dayState($sales, wib('2026-10-05'), null))->toBe('belum')
        ->and($cal->dayState($sales, wib('2026-10-02'), null))->toBe('tidak_absen')   // past Friday
        ->and($cal->dayState($sales, wib('2026-10-04'), null))->toBe('libur')
        ->and($cal->dayState($sales, wib('2026-10-05'), $late))->toBe('terlambat'); // older records keep their status

    $this->travelTo(wib('2026-10-06 00:05'));
    expect((new AttendanceCalendar)->dayState($sales, wib('2026-10-05'), null))->toBe('tidak_absen');
});

it('does not count days before the sales joined as working days', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    \App\Models\SalesProfile::factory()->create(['user_id' => $sales->id, 'joined_at' => '2026-10-05']);
    $cal = new AttendanceCalendar;

    expect($cal->for($sales, wib('2026-10-02')))->toBeNull()          // Friday, before joining
        ->and($cal->for($sales, wib('2026-10-05')))->not->toBeNull(); // first day
});
