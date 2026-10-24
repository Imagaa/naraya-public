<?php

use App\Enums\RedemptionStatus;
use App\Enums\Role;
use App\Models\Attendance;
use App\Models\Redemption;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\AttendanceSeeder;
use Database\Seeders\RedemptionSeeder;
use Database\Seeders\RewardSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    (new AreaSeeder())->run();
    (new UserSeeder())->run();
    (new RewardSeeder())->run();
});

it('seeds redemptions across all statuses with rejection reason and delivery proof', function () {
    (new RedemptionSeeder())->run();

    expect(Redemption::count())->toBeGreaterThanOrEqual(40);
    foreach (RedemptionStatus::cases() as $status) {
        expect(Redemption::where('status', $status)->exists())->toBeTrue();
    }
    expect(Redemption::where('status', RedemptionStatus::Ditolak)->whereNotNull('alasan_penolakan')->exists())->toBeTrue();
    expect(Redemption::where('status', RedemptionStatus::Diserahkan)->whereNotNull('bukti_path')->exists())->toBeTrue();
});

it('seeds 30 days of attendance per sales with at least one corrected entry, and leaves one sales with no recent attendance', function () {
    (new AttendanceSeeder())->run();

    $salesCount = User::where('role', Role::Sales)->count();
    expect(Attendance::count())->toBeGreaterThan($salesCount * 20);
    expect(Attendance::whereNotNull('dikoreksi_oleh')->exists())->toBeTrue();

    $idleSales = User::where('role', Role::Sales)->latest('id')->first();
    expect(Attendance::where('sales_id', $idleSales->id)->where('created_at', '>=', now()->subDays(7))->exists())->toBeFalse();

    expect(\App\Models\AttendanceLocation::where('nama', 'Kantor Naraya')->exists())->toBeTrue()
        ->and(Attendance::whereNull('status')->exists())->toBeFalse()
        ->and(Attendance::where('status', \App\Enums\AttendanceStatus::Terlambat)->exists())->toBeFalse(); // no set hours since 2026-10-12
});
