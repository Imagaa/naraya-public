<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Livewire\Admin\Attendances\Recap;
use App\Livewire\Admin\Attendances\RecapSales;
use App\Models\Attendance;
use App\Models\User;
use App\Support\AttendanceReport;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-08 18:00', 'Asia/Jakarta'));   // Thursday
    sundayOff(); // these fixtures count the month with Sundays off, as the schedule was then
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $mk = function (string $date, string $time, AttendanceType $tipe, AttendanceStatus $st, int $sel = 0) {
        Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => $tipe, 'status' => $st, 'selisih_menit' => $sel,
            'created_at' => Carbon::parse("$date $time", 'Asia/Jakarta')->utc()]);
    };
    // Oct 1 Thu on time, Oct 2 Fri late 20, Oct 3 Sat absent, Oct 4 Sun off, Oct 5 Mon late 10 + pulang cepat,
    // Oct 6 Tue on time, Oct 7 Wed absent, Oct 8 Thu on time
    $mk('2026-10-01', '07:58', AttendanceType::Masuk, AttendanceStatus::TepatWaktu);
    $mk('2026-10-02', '08:20', AttendanceType::Masuk, AttendanceStatus::Terlambat, 20);
    $mk('2026-10-05', '08:10:30', AttendanceType::Masuk, AttendanceStatus::Terlambat, 10);
    $mk('2026-10-05', '16:30', AttendanceType::Pulang, AttendanceStatus::PulangCepat, 30);
    $mk('2026-10-06', '07:50', AttendanceType::Masuk, AttendanceStatus::TepatWaktu);
    $mk('2026-10-08', '08:00', AttendanceType::Masuk, AttendanceStatus::TepatWaktu);
});

it('totals a month up to today', function () {
    $recap = AttendanceReport::month($this->sales, Carbon::parse('2026-10-01', 'Asia/Jakarta'));

    expect($recap['days'])->toHaveCount(8)
        ->and($recap['totals'])->toBe([
            'hari_kerja' => 7, 'libur_diambil' => 0, 'hadir' => 5, 'terlambat' => 2, 'menit_terlambat' => 30,
            'tidak_absen' => 2, 'pulang_cepat' => 1, 'persen_tepat' => 60,
        ]);
});

it('writes a performance note comparing with last month', function () {
    $totals = ['hari_kerja' => 22, 'hadir' => 21, 'terlambat' => 4, 'menit_terlambat' => 36, 'tidak_absen' => 1, 'pulang_cepat' => 0, 'persen_tepat' => 81];

    expect(AttendanceReport::note($totals, ['terlambat' => 7] + $totals))
        ->toBe('Terlambat 4x dari 22 hari kerja (rata-rata 9 mnt), 1x tidak absen. Membaik dibanding bulan lalu (7x).')
        ->and(AttendanceReport::note(['terlambat' => 0, 'tidak_absen' => 0] + $totals, null))
        ->toBe('Tidak pernah terlambat dari 22 hari kerja, 0x tidak absen.');
});

it('ranks sales by lateness on the overview', function () {
    $punctual = User::factory()->create(['role' => Role::Sales, 'name' => 'Ani']);
    Livewire::actingAs($this->admin)->test(Recap::class)
        ->assertSeeInOrder(['Budi', 'Ani'])
        ->assertSee('60%');
});

it('renders the per-sales recap with chart data, the note and the daily table', function () {
    Livewire::actingAs($this->admin)->test(RecapSales::class, ['user' => $this->sales])
        ->assertSee('att-checkin-chart', false)
        ->assertSee('att-trend-chart', false)
        ->assertSee('Terlambat 2x dari 7 hari kerja')
        ->assertSee('Terlambat 20 mnt')
        ->assertSee('Pulang cepat 30 mnt');
});

it('keeps hadir within working days when the sales also checks in on an off day', function () {
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'status' => AttendanceStatus::TanpaJadwal,
        'created_at' => Carbon::parse('2026-10-04 09:00', 'Asia/Jakarta')->utc()]);   // Sunday

    $t = AttendanceReport::month($this->sales, Carbon::parse('2026-10-01', 'Asia/Jakarta'))['totals'];
    expect($t['hadir'])->toBe(5)->and($t['hari_kerja'])->toBe(7)->and($t['persen_tepat'])->toBe(60);
});

it('counts a mid-month joiner only from the join date, and skips the comparison with a month before joining', function () {
    \App\Models\SalesProfile::factory()->create(['user_id' => $this->sales->id, 'joined_at' => '2026-10-05']);

    $t = AttendanceReport::month($this->sales, Carbon::parse('2026-10-01', 'Asia/Jakarta'))['totals'];
    expect($t['hari_kerja'])->toBe(4)->and($t['tidak_absen'])->toBe(1);   // Oct 5–8; Oct 7 absent

    Livewire::actingAs($this->admin)->test(RecapSales::class, ['user' => $this->sales])
        ->assertDontSee('dibanding bulan lalu');
});

it('marks rows recorded before schedules existed as not judged in the chart data', function () {
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Masuk, 'status' => null, 'selisih_menit' => null,
        'created_at' => Carbon::parse('2026-10-07 08:30', 'Asia/Jakarta')->utc()]);

    Livewire::actingAs($this->admin)->test(RecapSales::class, ['user' => $this->sales])
        ->assertViewHas('points', fn ($points) => $points->firstWhere('label', '7')['judged'] === false
            && $points->firstWhere('label', '7')['text'] === 'Data lama, belum dinilai'
            && $points->firstWhere('label', '6')['judged'] === true);
});
