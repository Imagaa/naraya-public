<?php

namespace App\Observers;

use App\Enums\CashDepositStatus;
use App\Models\CashDeposit;
use App\Notifications\AdminNotice;
use App\Notifications\SalesNotice;

class CashDepositObserver
{
    public function created(CashDeposit $deposit): void
    {
        AdminNotice::toAdmins('Setoran baru', 'Rp '.number_format($deposit->claimed_amount, 0, ',', '.')." dari {$deposit->salesUser?->name}", route('admin.deposits.show', $deposit, absolute: false), 'wallet');
    }

    public function updated(CashDeposit $deposit): void
    {
        if ($deposit->wasChanged('status') && in_array($deposit->status, [CashDepositStatus::Verified, CashDepositStatus::Resolved], true)
            && $deposit->claimed_amount < $deposit->expected_amount) {
            AdminNotice::toAdmins('Selisih setoran', "Setoran #{$deposit->id} {$deposit->salesUser?->name} kurang Rp ".number_format($deposit->expected_amount - $deposit->claimed_amount, 0, ',', '.'), route('admin.deposits.show', $deposit, absolute: false), 'wallet', 'warning');
        }

        if ($deposit->wasChanged('status') && $deposit->status === CashDepositStatus::Disputed) {
            AdminNotice::toAdmins('Setoran disengketakan', "Setoran #{$deposit->id} dari {$deposit->salesUser?->name} disengketakan", route('admin.deposits.show', $deposit, absolute: false), 'wallet', 'warning', urgent: true);
        }

        $tone = match ($deposit->status) {
            CashDepositStatus::Verified => 'success',
            CashDepositStatus::Disputed => 'warning',
            CashDepositStatus::Resolved => 'info',
            default => null,
        };

        if ($tone === null || ! $deposit->wasChanged('status') || SalesNotice::skipFor($deposit->salesUser)) {
            return;
        }

        $deposit->salesUser->notify(new SalesNotice(
            'Status setoran',
            'Setoran Rp '.number_format($deposit->claimed_amount, 0, ',', '.').': '.$deposit->status->label(),
            route('sales.deposits.index', absolute: false),
            'wallet',
            $tone,
        ));
    }
}
