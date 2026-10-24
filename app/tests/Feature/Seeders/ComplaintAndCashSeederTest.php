<?php

use App\Enums\CashCollectionStatus;
use App\Enums\CashDepositStatus;
use App\Enums\ComplaintStatus;
use App\Enums\ComplaintType;
use App\Enums\Role;
use App\Models\CashBookEntry;
use App\Models\CashDeposit;
use App\Models\Complaint;
use App\Models\User;
use Database\Seeders\AreaSeeder;
use Database\Seeders\CashSeeder;
use Database\Seeders\ComplaintSeeder;
use Database\Seeders\OrderSeeder;
use Database\Seeders\ProductSeeder;
use Database\Seeders\UserSeeder;

beforeEach(function () {
    (new AreaSeeder())->run();
    (new UserSeeder())->run();
    User::factory()->create(['role' => Role::Admin]);
    (new ProductSeeder())->run();
    (new OrderSeeder())->run();
});

it('seeds complaints covering both types and every status, including a credit adjustment', function () {
    (new ComplaintSeeder())->run();

    expect(Complaint::count())->toBeGreaterThanOrEqual(25);
    foreach (ComplaintType::cases() as $type) {
        expect(Complaint::where('tipe', $type)->exists())->toBeTrue();
    }
    foreach (ComplaintStatus::cases() as $status) {
        expect(Complaint::where('status', $status)->exists())->toBeTrue();
    }
    expect(Complaint::where('status', ComplaintStatus::Diresolusi)->whereNotNull('nominal_penyesuaian')->exists())->toBeTrue();
});

it('seeds cash collections and deposits including a dispute and a resolution, with backdated cash-book entries', function () {
    (new CashSeeder())->run();

    expect(CashDeposit::where('status', CashDepositStatus::Disputed)->exists())->toBeTrue();
    expect(CashDeposit::where('status', CashDepositStatus::Resolved)->exists())->toBeTrue();
    expect(\App\Models\CashCollection::where('status', CashCollectionStatus::Held)->exists())->toBeTrue();

    $verified = CashDeposit::where('status', CashDepositStatus::Verified)->first();
    if ($verified) {
        $entry = CashBookEntry::where('reference', 'Setoran #'.$verified->id)->first();
        expect($entry)->not->toBeNull();
        expect($entry->date->toDateString())->toBe($verified->created_at->toDateString());
    }
});
