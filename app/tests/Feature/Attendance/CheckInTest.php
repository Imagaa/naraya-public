<?php

use App\Enums\AttendanceStatus;
use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

const KANTOR = [-6.1753942, 106.8271528];

beforeEach(function () {
    Storage::fake('s3');
    $this->travelTo(Carbon::parse('2026-10-05 07:55', 'Asia/Jakarta')); // Monday
    $this->sales = User::factory()->create(['role' => Role::Sales, 'name' => 'Budi']);
    $this->kantor = AttendanceLocation::create(['nama' => 'Kantor Pusat', 'lat' => KANTOR[0], 'lng' => KANTOR[1], 'radius_m' => 100]);
});

function absen($test, array $overrides = [], ?User $as = null)
{
    $as ??= $test->sales;
    $token = $test->actingAs($as)->postJson(salesUrl('/absensi/sesi'))->assertOk()->json('token');

    return $test->actingAs($as)->postJson(salesUrl('/absensi/submit'), array_merge([
        'tipe' => 'masuk',
        'token' => $token,
        'latitude' => KANTOR[0] + 0.0002,   // ~22 m north
        'longitude' => KANTOR[1],
        'akurasi' => 12,
        'foto' => UploadedFile::fake()->image('absen.jpg', 640, 480),
        'idempotency_key' => (string) Str::uuid(),
    ], $overrides));
}

it('records a check-in with server time, location, judgement and a watermarked photo', function () {
    absen($this)->assertCreated()->assertJson(['hasil' => 'Tercatat']);

    $a = Attendance::sole();
    expect($a->tipe)->toBe(AttendanceType::Masuk)
        ->and($a->status)->toBe(AttendanceStatus::Tercatat)
        ->and($a->attendance_location_id)->toBe($this->kantor->id)
        ->and($a->jarak_m)->toBeBetween(15, 30)
        ->and($a->akurasi_m)->toBe(12)
        ->and($a->jadwal_jam)->toBeNull()
        ->and($a->flags)->toBe([]);
    expect(getimagesizefromstring(Storage::disk('s3')->get($a->foto_path))['mime'])->toBe('image/jpeg');
});

it('takes a check-in at any hour without calling it late', function () {
    $this->travelTo(Carbon::parse('2026-10-05 13:12:30', 'Asia/Jakarta'));
    absen($this)->assertCreated()->assertJson(['hasil' => 'Tercatat']);
    expect(Attendance::sole()->selisih_menit)->toBeNull();
});

it('requires a valid, unexpired, single-use token', function () {
    $submit = fn ($token, $tipe, $key) => $this->actingAs($this->sales)->postJson(salesUrl('/absensi/submit'), [
        'tipe' => $tipe, 'token' => $token, 'latitude' => KANTOR[0], 'longitude' => KANTOR[1], 'akurasi' => 10,
        'foto' => UploadedFile::fake()->image('a.jpg'), 'idempotency_key' => $key,
    ]);
    $expired = 'Sesi absen kedaluwarsa, buka kamera lagi.';

    $submit('made-up', 'masuk', 'a')->assertStatus(422)->assertJson(['message' => $expired]);

    $token = $this->actingAs($this->sales)->postJson(salesUrl('/absensi/sesi'))->json('token');
    $submit($token, 'masuk', 'b')->assertCreated();
    $submit($token, 'pulang', 'c')->assertStatus(422)->assertJson(['message' => $expired]);   // spent

    $late = $this->actingAs($this->sales)->postJson(salesUrl('/absensi/sesi'))->json('token');
    $this->travel(121)->seconds();
    $submit($late, 'pulang', 'd')->assertStatus(422)->assertJson(['message' => $expired]);   // expired
    expect(Attendance::count())->toBe(1);
});

it('accepts a token whose cached user id comes back as a string (redis)', function () {
    Cache::put('att-token:abc', (string) $this->sales->id, 120);
    $this->actingAs($this->sales)->postJson(salesUrl('/absensi/submit'), [
        'tipe' => 'masuk', 'token' => 'abc', 'latitude' => KANTOR[0], 'longitude' => KANTOR[1], 'akurasi' => 10,
        'foto' => UploadedFile::fake()->image('a.jpg'), 'idempotency_key' => 'k1',
    ])->assertCreated();
});

it('returns the existing record for a retried submit with the same idempotency key (token already spent)', function () {
    $key = (string) Str::uuid();
    absen($this, ['idempotency_key' => $key])->assertCreated();
    $this->actingAs($this->sales)->postJson(salesUrl('/absensi/submit'), [
        'tipe' => 'masuk', 'token' => 'spent', 'latitude' => KANTOR[0], 'longitude' => KANTOR[1], 'akurasi' => 10,
        'foto' => UploadedFile::fake()->image('a.jpg'), 'idempotency_key' => $key,
    ])->assertOk();
    expect(Attendance::count())->toBe(1);
});

it('rejects a weak GPS fix', function () {
    absen($this, ['akurasi' => 150])->assertStatus(422)
        ->assertJson(['message' => 'Sinyal GPS lemah (±150 m). Coba di area terbuka lalu ulangi.']);
});

it('rejects a device outside every allowed location, naming the nearest and the distance', function () {
    absen($this, ['latitude' => KANTOR[0] + 0.0108])->assertStatus(422)   // ~1.2 km
        ->assertJsonPath('message', fn ($m) => str_starts_with($m, 'Anda di luar area absen — 1,2 km dari Kantor Pusat'));
});

it('accepts a device inside a big-radius location even when a small-radius one is nearer', function () {
    $this->kantor->update(['radius_m' => 20]);
    $gudang = AttendanceLocation::create(['nama' => 'Gudang', 'lat' => KANTOR[0] + 0.004, 'lng' => KANTOR[1], 'radius_m' => 1000]);

    absen($this, ['latitude' => KANTOR[0] + 0.001])->assertCreated();   // 111 m from Kantor (r 20), 333 m from Gudang (r 1000)
    expect(Attendance::sole()->attendance_location_id)->toBe($gudang->id);
});

it('does not let a sales use a location assigned only to someone else; no location at all is rejected', function () {
    $other = User::factory()->create(['role' => Role::Sales]);
    $this->kantor->users()->attach($other);

    absen($this)->assertStatus(422)->assertJson(['message' => 'Belum ada lokasi absen untuk Anda, hubungi admin.']);
    absen($this, [], $other)->assertCreated();
});

it('rejects a duplicate tipe and a pulang without masuk, using the WIB day', function () {
    $this->travelTo(Carbon::parse('2026-10-05 06:30', 'Asia/Jakarta'));   // 23:30 UTC on the 4th
    absen($this, ['tipe' => 'pulang'])->assertStatus(422)->assertJson(['message' => 'Absen masuk dulu.']);
    absen($this)->assertCreated();
    absen($this)->assertStatus(422)->assertJson(['message' => 'Sudah absen masuk hari ini.']);
});

it('takes a pulang at any hour and marks an off-day check-in', function () {
    sundayOff(); // these tests exercise an off day in the schedule; since 2026-10-10 Sunday is a working day by default
    absen($this)->assertCreated();
    $this->travelTo(Carbon::parse('2026-10-05 11:40', 'Asia/Jakarta'));
    absen($this, ['tipe' => 'pulang'])->assertCreated()->assertJson(['hasil' => 'Tercatat']);

    $this->travelTo(Carbon::parse('2026-10-04 09:00', 'Asia/Jakarta'));   // Sunday
    absen($this)->assertCreated()->assertJson(['hasil' => 'Hari libur']);
});

it('flags impossible travel, identical coordinates and implausible accuracy without blocking', function () {
    // Medan, last night 23:50 WIB (yesterday, so it doesn't block today's check-in): ~1,420 km in ~8 h ≈ 175 km/h
    Attendance::factory()->create(['sales_id' => $this->sales->id, 'tipe' => AttendanceType::Pulang,
        'latitude' => 3.5952, 'longitude' => 98.6722, 'created_at' => Carbon::parse('2026-10-04 23:50', 'Asia/Jakarta')->utc()]);

    absen($this, ['akurasi' => 1])->assertCreated();
    expect(Attendance::latest('id')->first()->flags)->toBe(['lompatan', 'akurasi_janggal']);

    absen($this, ['tipe' => 'pulang', 'latitude' => KANTOR[0] + 0.0002, 'longitude' => KANTOR[1]])->assertCreated();
    expect(Attendance::latest('id')->first()->flags)->toBe(['koordinat_identik']);
});

it('serves today\'s schedule for the countdown', function () {
    $this->actingAs($this->sales)->getJson(salesUrl('/absensi/status'))
        ->assertOk()
        ->assertJson(['libur' => false, 'masuk_jam' => '08:00', 'batas_jam' => '08:10', 'pulang_jam' => '17:00', 'masuk' => null]);
});

it('blocks non-sales roles', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $this->actingAs($admin)->postJson(salesUrl('/absensi/sesi'))->assertForbidden();
});

it('refuses a second submit while the first one for the same sales is still being processed', function () {
    $lock = Cache::lock('att-submit:'.$this->sales->id, 15);
    $lock->get();

    absen($this)->assertStatus(422)->assertJson(['message' => 'Absen sebelumnya masih diproses, coba lagi sebentar.']);
    expect(Attendance::count())->toBe(0);

    $lock->release();
    absen($this)->assertCreated();
});
