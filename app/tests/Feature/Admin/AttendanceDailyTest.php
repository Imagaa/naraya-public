<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Livewire\Admin\Attendances\Index;
use App\Models\Attendance;
use App\Models\AttendanceHoliday;
use App\Models\SalesProfile;
use App\Models\User;
use App\Support\AttendanceReport;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-10-05 10:00', 'Asia/Jakarta'));   // Monday
    $this->admin = User::factory()->create(['role' => Role::Admin]);
    $this->onTime = User::factory()->create(['role' => Role::Sales, 'name' => 'Ani Tepat']);
    $this->late = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi Telat']);
    $this->absent = User::factory()->create(['role' => Role::Sales, 'name' => 'Cici Absen']);
    SalesProfile::factory()->create(['user_id' => $this->absent->id, 'phone' => '0812-3456-789']);
    $inactive = User::factory()->create(['role' => Role::Sales, 'name' => 'Dodi Nonaktif']);
    SalesProfile::factory()->create(['user_id' => $inactive->id, 'is_active' => false]);

    $at = fn ($t) => Carbon::parse("2026-10-05 {$t}", 'Asia/Jakarta')->utc();
    Attendance::factory()->create(['sales_id' => $this->onTime->id, 'tipe' => AttendanceType::Masuk, 'created_at' => $at('07:55'), 'status' => AttendanceStatus::TepatWaktu]);
    Attendance::factory()->create(['sales_id' => $this->late->id, 'tipe' => AttendanceType::Masuk, 'created_at' => $at('08:25'), 'status' => AttendanceStatus::Terlambat, 'selisih_menit' => 25, 'flags' => ['lompatan']]);
});

it('builds one row per active sales with the day state', function () {
    $rows = AttendanceReport::day(Carbon::parse('2026-10-05', 'Asia/Jakarta'))->keyBy(fn ($r) => $r['sales']->name);

    expect($rows->keys()->sort()->values()->all())->toBe(['Ani Tepat', 'Budi Telat', 'Cici Absen'])
        ->and($rows['Ani Tepat']['state'])->toBe('hadir')
        ->and($rows['Budi Telat']['state'])->toBe('terlambat')
        ->and($rows['Cici Absen']['state'])->toBe('belum'); // no set hours: a no-show is "belum" until the day is over
});

it('counts the four tabs and filters by them', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('counts', fn ($c) => $c['all'] === 3 && $c['hadir'] === 2 && $c['terlambat'] === 1 && $c['tidak_absen'] === 0 && $c['belum'] === 1)
        ->assertSee('Terlambat 25 mnt')
        ->call('setFilter', 'tidak_absen')
        ->assertSee('Cici Absen')->assertDontSee('Ani Tepat')
        ->assertSee('wa.me/628123456789', false);
});

it('shows Belum absen (not Tidak absen) before the tolerance today', function () {
    $this->travelTo(Carbon::parse('2026-10-05 08:05', 'Asia/Jakarta'));
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertViewHas('counts', fn ($c) => $c['tidak_absen'] === 0 && $c['belum'] === 1);
});

it('treats an off-day check-in as present, never absent, and shows the holiday banner', function () {
    AttendanceHoliday::create(['tanggal' => '2026-10-02', 'nama' => 'Libur Kantor']);
    Attendance::factory()->create(['sales_id' => $this->absent->id, 'tipe' => AttendanceType::Masuk,
        'created_at' => Carbon::parse('2026-10-02 09:00', 'Asia/Jakarta')->utc(), 'status' => AttendanceStatus::TanpaJadwal]);

    Livewire::actingAs($this->admin)->test(Index::class)->set('date', '2026-10-02')
        ->assertSee('Libur: Libur Kantor')
        ->assertViewHas('counts', fn ($c) => $c['tidak_absen'] === 0 && $c['hadir'] === 1 && $c['terjadwal'] === 0);
});

it('moves between days and never into the future', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('shiftDay', -1)->assertSet('date', '2026-10-04')
        ->call('shiftDay', 1)->call('shiftDay', 1)->assertSet('date', '2026-10-05');
});

it('keeps the admin correction with a required reason', function () {
    $a = Attendance::where('sales_id', $this->late->id)->sole();
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('startCorrecting', $a->id)->call('correct')->assertHasErrors('koreksiAlasan')
        ->set('koreksiAlasan', 'GPS kantor bermasalah')->call('correct')->assertHasNoErrors();
    expect($a->fresh()->koreksi_alasan)->toBe('GPS kantor bermasalah')->and($a->fresh()->dikoreksi_oleh)->toBe($this->admin->id);
});

it('blocks non-admin roles from the attendance list', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/attendances')
        ->assertStatus(403);
});

it('words the WhatsApp follow-up for the day being viewed', function () {
    Livewire::actingAs($this->admin)->test(Index::class)->call('setFilter', 'tidak_absen')
        ->assertSee(rawurlencode('belum absen masuk hari ini'), false);

    $this->absent->salesProfile->update(['joined_at' => '2026-09-01']);   // was on the team on the 2nd
    Livewire::actingAs($this->admin)->test(Index::class)->set('date', '2026-10-02')->call('setFilter', 'tidak_absen')
        ->assertSee(rawurlencode('belum absen masuk pada 2 Okt'), false)
        ->assertDontSee(rawurlencode('hari ini'), false);
});

it('gives each row one visible action and puts the rest in a popover menu', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSeeHtml('popover')
        ->assertSee('Koreksi masuk')
        ->assertSee('Lihat rekap');
});

it('lets admin back out of a correction', function () {
    $a = Attendance::where('sales_id', $this->late->id)->sole();
    Livewire::actingAs($this->admin)->test(Index::class)
        ->call('startCorrecting', $a->id)->set('koreksiAlasan', 'x')
        ->call('cancelCorrecting')
        ->assertSet('correctingId', null)->assertSet('koreksiAlasan', '');
});

it('stacks each check-in as time over its status pill', function () {
    Livewire::actingAs($this->admin)->test(Index::class)
        ->assertSeeHtmlInOrder(['class="att-stamp"', '08:25', 'Terlambat 25 mnt']);
});
