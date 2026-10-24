<?php

namespace Database\Factories;

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class AttendanceFactory extends Factory
{
    protected $model = Attendance::class;

    public function definition(): array
    {
        return [
            'sales_id' => User::factory()->state(['role' => Role::Sales]),
            'tipe' => AttendanceType::Masuk,
            'latitude' => fake()->latitude(),
            'longitude' => fake()->longitude(),
            'idempotency_key' => (string) \Illuminate\Support\Str::uuid(),
            'status' => \App\Enums\AttendanceStatus::TepatWaktu,
            'selisih_menit' => 0,
        ];
    }
}
