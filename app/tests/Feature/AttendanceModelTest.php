<?php

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\User;

it('casts tipe to the enum and stores relations', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);
    $admin = User::factory()->create(['role' => Role::Admin]);

    $attendance = Attendance::factory()->create([
        'sales_id' => $sales->id,
        'tipe' => AttendanceType::Masuk,
        'dikoreksi_oleh' => $admin->id,
    ]);

    expect($attendance->tipe)->toBe(AttendanceType::Masuk);
    expect($attendance->salesUser->id)->toBe($sales->id);
    expect($attendance->correctedBy->id)->toBe($admin->id);
});

it('requires a unique idempotency_key', function () {
    $key = (string) \Illuminate\Support\Str::uuid();
    Attendance::factory()->create(['idempotency_key' => $key]);

    expect(fn () => Attendance::factory()->create(['idempotency_key' => $key]))
        ->toThrow(\Illuminate\Database\QueryException::class);
});
