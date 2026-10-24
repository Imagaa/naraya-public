<?php

namespace App\Observers;

use App\Enums\RedemptionStatus;
use App\Models\Redemption;
use App\Notifications\AdminNotice;
use App\Notifications\BuyerNotice;
use App\Notifications\SalesNotice;

class RedemptionObserver
{
    public function created(Redemption $redemption): void
    {
        AdminNotice::toAdmins('Penukaran hadiah baru', "{$redemption->user?->name} menukar {$redemption->reward?->name}", route('admin.redemptions.show', $redemption, absolute: false), 'gift');
    }

    public function updated(Redemption $redemption): void
    {
        if (! $redemption->wasChanged('status')) {
            return;
        }

        if ($redemption->status === RedemptionStatus::Disetujui && ! SalesNotice::skipFor($redemption->sales)) {
            $redemption->sales->notify(new SalesNotice(
                'Hadiah perlu diserahkan',
                "{$redemption->reward->name} untuk {$redemption->user->name}",
                route('sales.redemptions.index', absolute: false),
                'gift',
            ));
        }

        if ($redemption->status === RedemptionStatus::Diajukan || BuyerNotice::skipFor($redemption->user)) {
            return;
        }

        $good = in_array($redemption->status, [RedemptionStatus::Disetujui, RedemptionStatus::Diserahkan], true);

        $redemption->user->notify(new BuyerNotice(
            'Penukaran hadiah',
            "Penukaran {$redemption->reward->name}: {$redemption->status->label()}",
            route('rewards.index', absolute: false),
            'gift',
            $good ? 'success' : 'warning',
        ));
    }
}
