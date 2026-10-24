<?php

namespace Database\Seeders;

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\AttendanceLocation;
use App\Models\User;
use App\Support\AttendanceCalendar;
use App\Support\BusinessDay;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class AttendanceSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', Role::Admin)->first();
        $salesUsers = User::where('role', Role::Sales)->orderBy('id')->get();
        $kantor = AttendanceLocation::firstOrCreate(['nama' => 'Kantor Naraya'], ['lat' => -6.1753942, 'lng' => 106.8271528, 'radius_m' => 150, 'is_active' => true]);
        $calendar = new AttendanceCalendar;

        // Leave the last Sales with no attendance in the last 7 days (edge case).
        $idleSalesId = $salesUsers->last()->id;

        foreach ($salesUsers as $sales) {
            $idle = $sales->id === $idleSalesId;
            for ($daysAgo = $idle ? 20 : 30; $daysAgo >= ($idle ? 10 : 0); $daysAgo--) {
                $day = $calendar->for($sales, BusinessDay::today()->subDays($daysAgo));
                if ($day === null || ($daysAgo > 0 && fake()->boolean(6))) {
                    continue; // off day, or an occasional absence for the "Tidak absen" tab
                }

                $masukAt = $day['masuk']->copy()->addMinutes(fake()->boolean(25) ? fake()->numberBetween(11, 40) : fake()->numberBetween(-20, 9));
                if ($masukAt->isFuture()) {
                    continue;
                }
                $this->record($sales, AttendanceType::Masuk, $masukAt, $kantor, $calendar);

                $pulangAt = $day['pulang']->copy()->addMinutes(fake()->boolean(10) ? -fake()->numberBetween(5, 45) : fake()->numberBetween(0, 30));
                if (! $pulangAt->isFuture()) {
                    $this->record($sales, AttendanceType::Pulang, $pulangAt, $kantor, $calendar);
                }
            }

            // One corrected entry per sales.
            Attendance::where('sales_id', $sales->id)->inRandomOrder()->first()?->update([
                'koreksi_alasan' => 'Lupa absen tepat waktu, dikoreksi berdasarkan laporan lisan',
                'dikoreksi_oleh' => $admin?->id,
            ]);
        }
    }

    private function record(User $sales, AttendanceType $tipe, Carbon $at, AttendanceLocation $kantor, AttendanceCalendar $calendar): void
    {
        Attendance::create([
            'sales_id' => $sales->id,
            'tipe' => $tipe,
            'latitude' => $kantor->lat + fake()->randomFloat(6, -0.0006, 0.0006),
            'longitude' => $kantor->lng + fake()->randomFloat(6, -0.0006, 0.0006),
            'attendance_location_id' => $kantor->id,
            'jarak_m' => fake()->numberBetween(5, 90),
            'akurasi_m' => fake()->numberBetween(5, 30),
            ...$calendar->judge($sales, $tipe, $at),
            'idempotency_key' => (string) Str::uuid(),
            'created_at' => $at->copy()->utc(),
            'updated_at' => $at->copy()->utc(),
        ]);
    }
}
