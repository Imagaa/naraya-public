<?php

use App\Enums\AttendanceType;
use App\Enums\Role;
use App\Livewire\Admin\Reports\Absensi;
use App\Models\Attendance;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->admin = User::factory()->create(['role' => Role::Admin]);
});

it('lists attendance records within the date range', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    Attendance::factory()->create([
        'sales_id' => $sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => '2026-09-15',
    ]);
    Attendance::factory()->create([
        'sales_id' => $sales->id, 'tipe' => AttendanceType::Masuk, 'created_at' => '2026-08-15',
    ]);

    $rows = Livewire::actingAs($this->admin)
        ->test(Absensi::class)
        ->set('startDate', '2026-09-01')
        ->set('endDate', '2026-09-30')
        ->instance()
        ->rows();

    expect($rows)->toHaveCount(1);
});

it('blocks non-admin roles', function () {
    $sales = User::factory()->create(['role' => Role::Sales]);

    $this->actingAs($sales)
        ->get('http://admin.'.config('app.domain').'/laporan/absensi')
        ->assertStatus(403);
});
