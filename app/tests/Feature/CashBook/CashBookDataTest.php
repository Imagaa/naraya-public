<?php

use App\Enums\CashBookEntryKind;
use App\Enums\CashBookEntryType;
use App\Enums\CashDepositStatus;
use App\Enums\PaymentStatus;
use App\Enums\Role;
use App\Livewire\Admin\Deposits\Show as DepositShow;
use App\Models\CashBookEntry;
use App\Models\CashBookSetting;
use App\Models\CashDeposit;
use App\Models\Order;
use App\Models\User;
use Livewire\Livewire;

it('signs amounts by type and soft deletes', function () {
    $in = CashBookEntry::factory()->create(['type' => CashBookEntryType::Pemasukan, 'amount' => 700]);
    $out = CashBookEntry::factory()->create(['type' => CashBookEntryType::Pengeluaran, 'amount' => 200]);

    expect($in->signedAmount())->toBe(700)->and($out->signedAmount())->toBe(-200)->and($in->kind)->toBe(CashBookEntryKind::Manual);

    $out->delete();
    expect(CashBookEntry::count())->toBe(1)->and(CashBookEntry::withTrashed()->count())->toBe(2);
});

it('opens a 15 minute edit window from creation', function () {
    $this->freezeTime();
    $entry = CashBookEntry::factory()->create();

    expect($entry->isWithinEditWindow())->toBeTrue();
    $this->travel(15)->minutes();
    expect($entry->fresh()->isWithinEditWindow())->toBeFalse();
});

it('keeps one settings row with closing day 5 by default', function () {
    expect(CashBookSetting::current()->closing_day)->toBe(5)->and(CashBookSetting::count())->toBe(1);
});

it('stamps paid_at when an order\'s payment succeeds, once', function () {
    $this->freezeTime();
    $order = Order::factory()->create(['payment_method' => 'qris', 'payment_status' => PaymentStatus::Pending]);
    expect($order->paid_at)->toBeNull();

    $order->update(['payment_status' => PaymentStatus::Paid]);
    $stamp = $order->fresh()->paid_at;
    expect($stamp)->not->toBeNull();

    $this->travel(1)->hours();
    $order->update(['needs_payment_review' => true]);
    expect($order->fresh()->paid_at->equalTo($stamp))->toBeTrue();
});

it('stamps verified_at when admin verifies a matching deposit', function () {
    $admin = User::factory()->create(['role' => Role::Admin]);
    $deposit = CashDeposit::factory()->create(['status' => CashDepositStatus::Submitted, 'expected_amount' => 5000, 'claimed_amount' => 5000]);

    Livewire::actingAs($admin)->test(DepositShow::class, ['deposit' => $deposit])->call('verify');

    expect($deposit->fresh())->status->toBe(CashDepositStatus::Verified)->verified_at->not->toBeNull();
});
